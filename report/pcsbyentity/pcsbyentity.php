<?php

/**
 * -------------------------------------------------------------------------
 *  LICENSE
 *
 * This file is part of Reports plugin for GLPI.
 *
 * Reports is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Reports is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Reports. If not, see <http://www.gnu.org/licenses/>.
 *
 * @authors   Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 * @copyright Copyright (c) 2009-2026 Reports plugin team
 * @license   AGPL License 3.0 or (at your option) any later version
 * @link      https://github.com/InfotelGLPI/reports
 * @link      http://www.glpi-project.org/
 * @package   reports
 * @since     2009
 *            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * --------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;

function cmpStat($a, $b)
{
    return $a["tot"] - $b["tot"];
}


/**
 * Counters of every entity, sorted by count.
 *
 * @return array<int, array{class: string, cells: array}> rows of the result table
 */
function doStatBis($table, $entities, $header)
{
    global $DB;

    // Compute stat
    $counts = [];
    foreach ($entities as $entity) {
        // Count for this entity
        $criteria = [
            'SELECT' => ['states_id',
                'COUNT' => 'id AS cpt'],
            'FROM' => $table,
            'WHERE' => [
                'is_deleted' => 0,
                'is_template' => 0,
                'entities_id' => $entity,
            ],
            'GROUPBY' => ['states_id'],
        ];

        $iterator = $DB->request($criteria);

        $counts[$entity] = [];
        foreach ($iterator as $data) {
            $counts[$entity][$data["states_id"]] = $data["cpt"];
        }

        $counts[$entity]["tot"] = 0;
        foreach ($header as $id => $name) {
            if (isset($counts[$entity][$id])) {
                $counts[$entity]["tot"] += $counts[$entity][$id];
            } else {
                $counts[$entity][$id] = 0;
            }
        }
    }

    // Sort result
    uasort($counts, "cmpStat");

    // Build result
    $rows = [];
    $total["tot"] = 0;
    foreach ($header as $id => $name) {
        $total[$id] = 0;
    }
    foreach ($counts as $entity => $count) {
        if ($count["tot"]) {
            $Ent = new Entity();
            $Ent->getFromDB($entity);

            $cells = [
                ['value' => $entity ? $Ent->fields["name"] : __('Root entity'), 'class' => 'left'],
                ['value' => $count["tot"], 'class' => 'right'],
            ];
            $total["tot"] += $count["tot"];
            foreach ($header as $id => $name) {
                $cells[] = ['value' => $count[$id], 'class' => 'right'];
                $total[$id] += $count[$id];
            }
            $rows[] = ['class' => 'tab_bg_2', 'cells' => $cells];
        }
    }

    // Total
    if (count($entities) > 1) {
        $cells = [
            ['value' => __('Total'), 'class' => 'left'],
            ['value' => $total["tot"], 'class' => 'right'],
        ];
        foreach ($header as $id => $name) {
            $cells[] = ['value' => $total[$id], 'class' => 'right'];
        }
        $rows[] = ['class' => 'tab_bg_1', 'cells' => $cells];
    }

    return $rows;
}


/**
 * Counters of an entity and of its children, as a tree.
 *
 * @param array $rows rows of the result table, completed by the call
 *
 * @return array counters of the entity, children included
 */
function doStat($table, $entity, $header, $level = 0, array &$rows = [])
{
    global $DB;

    $Ent = new Entity();
    $Ent->getFromDB($entity);

    // Count for this entity
    $criteria = [
        'SELECT' => ['states_id',
            'COUNT' => 'id AS cpt'],
        'FROM' => $table,
        'WHERE' => [
            'is_deleted' => 0,
            'is_template' => 0,
            'entities_id' => $entity,
        ],
        'GROUPBY' => ['states_id'],
    ];

    $iterator = $DB->request($criteria);
    $count  = [];
    foreach ($iterator as $data) {
        $count[$data["states_id"]] = $data["cpt"];
    }

    $count["tot"] = 0;
    foreach ($header as $id => $name) {
        if (isset($count[$id])) {
            $count["tot"] += $count[$id];
        } else {
            $count[$id] = 0;
        }
    }

    $entity_name = $entity ? $Ent->fields["name"] : __('Root entity');

    // Counters for this entity
    if ($count["tot"] > 0) {
        $cells = [
            ['value' => $entity_name, 'indent' => $level],
            ['value' => $count["tot"], 'class' => 'right'],
        ];
        foreach ($header as $id => $name) {
            $cells[] = ['value' => $count[$id], 'class' => 'right'];
        }
        $rows[] = ['class' => 'tab_bg_2', 'cells' => $cells];
    }

    // Call for Childs
    $save = $count["tot"];
    doStatChilds($table, $entity, $header, $count, $level + 1, $rows);

    // Total (Current+Childs)
    if ($save != $count["tot"]) {
        $cells = [
            ['value' => sprintf(__('%1$s %2$s'), __('Total'), $entity_name), 'indent' => $level],
            ['value' => $count["tot"], 'class' => 'right'],
        ];
        foreach ($header as $id => $name) {
            $cells[] = ['value' => $count[$id], 'class' => 'right'];
        }
        $rows[] = ['class' => 'tab_bg_1', 'cells' => $cells];
    }
    return $count;
}


function doStatChilds($table, $entity, $header, &$total, $level, array &$rows = [])
{
    global $DB;

    // Search child entities, restricted to the entities the current profile is allowed to see.
    // Without this restriction, the recursion walks down the whole sub tree of the active entity,
    // including children the profile has no access to (non recursive entity assignment).
    $criteria = ['SELECT' => ['id', 'name'],
        'FROM' => 'glpi_entities',
        'WHERE'  => ['entities_id' => $entity],
        'ORDER'  => 'name'];
    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        'glpi_entities',
        'id',
        $_SESSION['glpiactiveentities'] ?? [],
        false,
    );

    $result = $DB->request($criteria);

    foreach ($result as $data) {
        // Defense in depth: never descend into an entity outside the active perimeter
        if (!Session::haveAccessToEntity($data["id"])) {
            continue;
        }
        $fille = doStat($table, $data["id"], $header, $level, $rows);
        foreach ($header as $id => $name) {
            $total[$id] += $fille[$id];
        }
        $total["tot"] += $fille["tot"];
    }
}

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

$dbu = new DbUtils();

Session::checkRight(\GlpiPlugin\Reports\Report::getRightName('pcsbyentity'), READ);
//TRANS: The name of the report = Number of items by entity
Html::header(__('Number of items by entity', 'reports'), '', "utils", "report");

Report::title();

$choix = [\Computer::class         => _n('Computer', 'Computers', 2),
    \Monitor::class          => _n('Monitor', 'Monitors', 2),
    \Printer::class          => _n('Printer', 'Printers', 2),
    \NetworkEquipment::class => __('Networking'),
    \Phone::class            => _n('Phone', 'Phones', 2)];

// The types the profile is allowed to read are computed once: the dropdown below is built from
// this list and so is the guard of the result branch, which used to test membership of the static
// list only and produced the report for any type once the POST was replayed.
$allowed_types = [];
foreach ($choix as $id => $name) {
    $item = new $id();
    if ($item->canView()) {
        $allowed_types[$id] = $name;
    }
}

$posted_type = (isset($_POST["type"]) && is_scalar($_POST["type"])) ? (string) $_POST["type"] : '';
$posted_sort = (isset($_POST["sort"]) && is_scalar($_POST["sort"]) && ($_POST["sort"] > 0)) ? 1 : 0;

// ---------- Form ------------
$cells = [[
    'name'  => 'type',
    'label' => __('Item type'),
    'field' => Dropdown::showFromArray('type', ['' => Dropdown::EMPTY_VALUE] + $allowed_types, [
        'value'   => $posted_type,
        'display' => false,
    ]),
]];
if (count($_SESSION["glpiactiveentities"]) > 1) {
    $cells[] = [
        'name'  => 'sort',
        'label' => __('Display', 'reports'),
        'field' => Dropdown::showFromArray('sort', [
            0 => __('Entity tree', 'reports'),
            1 => __('Sort by count', 'reports'),
        ], [
            'value'   => $posted_sort,
            'display' => false,
        ]),
    ];
}
$page = [
    'form' => [
        'action'     => $_SERVER["REQUEST_URI"],
        'title'      => __('Number of items by entity', 'reports'),
        'nb_columns' => 2,
        'rows'       => array_map(static fn(array $cell): array => ['cells' => [$cell]], $cells),
        'submit'     => ['name' => 'search', 'value' => '1', 'label' => __('Search')],
    ],
];

// --------------- Result -------------
if ($posted_type !== '' && !array_key_exists($posted_type, $allowed_types)) {
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

if ($posted_type !== '') {
    $header_cells = [
        ['value' => __('Entity')],
        ['value' => __('Total')],
        ['value' => __('Unknown', 'reports')],
    ];

    $criteria = [
        'SELECT' => [\State::getTable() . '.id', \State::getTable() . '.name'],
        'FROM' => \State::getTable(),
        'LEFT JOIN' => [
            DropdownVisibility::getTable() => [
                'ON' => [
                    DropdownVisibility::getTable() => 'items_id',
                    \State::getTable() => 'id', [
                        'AND' => [
                            DropdownVisibility::getTable() . '.itemtype' => \State::getType(),
                        ],
                    ],
                ],
            ],
        ],
        'WHERE' => [
            DropdownVisibility::getTable() . '.itemtype' => \State::getType(),
            DropdownVisibility::getTable() . '.visible_itemtype' => strtolower($posted_type),
            DropdownVisibility::getTable() . '.is_visible' => 1,
        ],
    ];
    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        \State::getTable(),
    );

    $iterator = $DB->request($criteria);

    $header[0] = __('Unknown', 'reports');
    foreach ($iterator as $data) {
        $header[$data["id"]] = $data["name"];
        $header_cells[] = ['value' => $data["name"]];
    }

    $rows = [];
    if ($posted_sort) {
        $rows = doStatBis($dbu->getTableForItemType($posted_type), $_SESSION["glpiactiveentities"], $header);
    } else {
        doStat($dbu->getTableForItemType($posted_type), $_SESSION["glpiactive_entity"], $header, 0, $rows);
    }

    $page['tables'] = [[
        'class'       => 'tab_cadre',
        'header_rows' => [['cells' => $header_cells]],
        'rows'        => $rows,
    ]];
} elseif (isset($_POST["type"])) {
    $page['messages'] = [['type' => 'danger', 'text' => __('Selection of type is mandatory', 'reports')]];
}

TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', $page);
Html::footer();
