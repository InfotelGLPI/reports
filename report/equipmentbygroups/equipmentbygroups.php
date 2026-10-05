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

$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0; // Not really a big SQL request

Session::checkRight("plugin_reports_equipmentbygroups", READ);

Html::header(__('List all devices of a group, ordered by users', 'reports'), '', "utils", "report");

Report::title();

global $DB;

// The request used to be merged back into $_GET, which rewrote a superglobal for the rest of
// the page (and for anything included after it). Keep the merged values in a local array and
// hand it to the form explicitly.
$params = getValues($_GET, $_POST);
if (isset($_GET["reset_search"])) {
    $params["groups_id"] = 0;
}

$page = ['form' => getSearchForm($params), 'tables' => []];

$where = ['entities_id' => $_SESSION["glpiactive_entity"],
    'is_itemgroup' => 1,
];
// The dropdown used to be named "group" while this condition read "groups_id", a key nothing
// ever filled: the report listed every item group of the active entity whatever the user had
// selected. Both ends now use the name Group::dropdown() defaults to.
if (!empty($params["groups_id"])) {
    $where = [
        'entities_id' => [$_SESSION["glpiactive_entity"]],
        'id' => (int) $params['groups_id'],
    ];
}

$result = $DB->request([
    'SELECT' => ['id', 'name'],
    'FROM' => 'glpi_groups',
    'WHERE' => $where,
    'ORDER' => 'name',
]);
$last_group_id = -1;

foreach ($result as $datas) {

    if ($last_group_id != $datas["id"]) {
        $page['tables'][] = [
            'class'       => 'tab_cadre',
            'header_rows' => [['cells' => [['value' => sprintf(__('%1$s: %2$s'), __('Group'), $datas['name'])]]]],
        ];
        $last_group_id = $datas["id"];
    }

    $table = getObjectsByGroupAndEntity($datas["id"], $_SESSION["glpiactive_entity"]);
    if ($table !== null) {
        $page['tables'][] = $table;
    }
}

TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', $page);

Html::footer();


/**
 * Group form, as variables of the shared criteria form template
 *
 * @param array<string, mixed> $params Merged request values of the report
 **/
function getSearchForm(array $params): array
{
    // The reset link points at this very page; the path used to be hard-coded under /plugins,
    // which broke on an instance where the plugin lives in marketplace/.
    $reset_href = (string) parse_url((string) $_SERVER["REQUEST_URI"], PHP_URL_PATH) . '?reset_search=reset_search';

    return [
        'action'     => $_SERVER["REQUEST_URI"],
        'nb_columns' => 2,
        'rows'       => [[
            'cells' => [[
                'name'  => 'groups_id',
                'label' => __('Group'),
                'field' => Group::dropdown([
                    'name'      => "groups_id",
                    'value'     => is_scalar($params["groups_id"]) ? (int) $params["groups_id"] : 0,
                    'entity'    => $_SESSION["glpiactive_entity"],
                    'condition' => ['is_itemgroup' => 1],
                    'display'   => false,
                ]),
            ]],
        ]],
        'submit'     => ['name' => 'search', 'value' => 'Valider', 'label' => _x('button', 'Post')],
        'reset'      => ['href' => $reset_href, 'label' => __('Blank')],
    ];
}


function getValues($get, $post)
{
    $get = array_merge($get, $post);

    if (!isset($get["groups_id"])) {
        $get["groups_id"] = 0;
    }
    return $get;
}




/**
 * Table of all devices by group
 *
 * @param $group_id - the group ID
 * @param $entity - the current entity
 *
 * @return array|null variables of the table template, null when the group holds no device
 **/
function getObjectsByGroupAndEntity($group_id, $entity)
{
    global $DB, $CFG_GLPI;

    $table = null;

    // Two problems in the original loop. It removed entries from $CFG_GLPI['asset_types']
    // while walking it, which mutated the global list for the rest of the request, and it
    // had no continue, so Certificate and SoftwareLicense were queried all the same. And no
    // read right was ever confronted: the only guards on this report are the plugin right
    // and the active entity, so a profile with no right on Printer, NetworkEquipment, PDU,
    // Enclosure or a custom asset still received their name, serial number, inventory and
    // immobilisation numbers, supplier and purchase date. report/pcsbyentity already filters
    // its type list with canView() for exactly that reason.
    // The immobilization number, the supplier and the purchase date all come from glpi_infocoms,
    // which the core gates behind its own dedicated "infocom" right: reading an asset never grants
    // the right to read its purchase record. The plugin right must not stand in for it, so those
    // three columns -- and the join that feeds them -- are only built when the profile holds it.
    $can_view_infocom = Session::haveRight(Infocom::$rightname, READ);

    $asset_types = $CFG_GLPI["asset_types"];
    foreach ($asset_types as $itemtype) {
        if (($itemtype == 'Certificate') || ($itemtype == 'SoftwareLicense')) {
            continue;
        }
        $item = new $itemtype();
        if (!$item->canView()) {
            continue;
        }

        $criteria = [
            'SELECT' => [
                $item->getTable() . '.id',
                'name',
                'groups_id',
                'serial',
                'otherserial',
                ...($can_view_infocom ? [
                    'immo_number',
                    'suppliers_id',
                    'buy_date',
                ] : []),
            ],
            'FROM' => $item->getTable(),
            'LEFT JOIN' => [
                ...($can_view_infocom ? [
                    'glpi_infocoms' => [
                        'FKEY' => [
                            $item->getTable() => 'id',
                            'glpi_infocoms' => 'items_id',
                        ],
                        ['glpi_infocoms.itemtype' => $itemtype],
                    ],
                ] : []),
                'glpi_groups_items' => [
                    'FKEY' => [
                        $item->getTable() => 'id',
                        'glpi_groups_items' => 'items_id',
                    ],
                    ['glpi_groups_items.itemtype' => $itemtype],
                ],
            ],
            'WHERE' => [
                'groups_id' => $group_id,
                $item->getTable() . '.entities_id' => $entity,
            ],
        ];

        if ($item->maybeTemplate()) {
            $criteria['WHERE'][] = ['is_template' => 0];
        }

        if ($item->maybeDeleted()) {
            $criteria['WHERE'][] = ['is_deleted' => 0];
        }

        $iterator = $DB->request($criteria);

        if (count($iterator) > 0) {
            if ($table === null) {
                $headers = [__('Type'), __('Name'), __('Serial number'), __('Inventory number')];
                if ($can_view_infocom) {
                    $headers[] = __('Immobilization number');
                    $headers[] = __('Supplier');
                    $headers[] = __('Date of purchase');
                }
                $table = [
                    'class'       => 'tab_cadre_fixehov',
                    'header_rows' => [['cells' => array_map(static fn($title) => ['value' => $title], $headers)]],
                    'rows'        => [],
                ];
            }
            $table['rows'] = array_merge($table['rows'], getUserDevicesRows($itemtype, $iterator, $can_view_infocom));
        }

    }

    return $table;
}


/**
 * Rows of all device for a group
 *
 * @param $type - the objet type
 * @param $result - the resultset of all the devices found
 * @param $can_view_infocom - whether the profile holds the core "infocom" right
 *
 * @return array<int, array{class: string, cells: array}>
 **/
function getUserDevicesRows($type, $result, $can_view_infocom)
{
    global $CFG_GLPI;

    $rows = [];
    $item = new $type();
    foreach ($result as $data) {
        // The label used to append the group id rather than the item id when the ids are shown
        $name = (string) $data["name"];
        if ($CFG_GLPI["is_ids_visible"] || $name === '') {
            $name = sprintf(__('%1$s (%2$s)'), $name, (int) $data["id"]);
        }
        $link_cell = [
            'value' => $name,
            'href'  => Toolbox::getItemTypeFormURL($type) . "?id=" . (int) $data["id"],
            'class' => 'center',
        ];
        // A $linktype was built here from a $groups array that this function never receives and
        // never declares -- the lookup was made on one key ($data["id"]) and the label read from
        // another ($data["groups_id"]) -- and the result was assigned to a variable no line of the
        // plugin ever echoed. There is no column to restore either: the header above emits Type,
        // Name, Serial, Inventory and, under the infocom right, the three financial cells, which
        // is exactly what the row below emits. The block was therefore dead from both ends and is
        // dropped rather than repaired on a guess. Should a "linked items" column ever be wanted,
        // it has to be added to the header as well, and the itemtype it walks must go through
        // is_a($linktype, CommonDBTM::class, true) then canView() before any query, the way
        // report/histohard/histohard.php does.
        $cells = [
            ['value' => $item->getTypeName(), 'class' => 'center'],
            $link_cell,
            ['value' => (string) ($data["serial"] ?? ''), 'class' => 'center'],
            ['value' => (string) ($data["otherserial"] ?? ''), 'class' => 'center'],
        ];

        // The three financial cells below are only selected -- and only headed -- when the core
        // "infocom" right is held, so they must be skipped here as well to keep the row aligned.
        if ($can_view_infocom) {
            $cells[] = ['value' => (string) ($data["immo_number"] ?? ''), 'class' => 'center'];
            // Security (stored XSS): Dropdown::getDropdownName() returns the label exactly as it
            // is stored, and a supplier name is writable by any holder of the dropdown right or
            // by an import: the template escapes it.
            $cells[] = [
                'value' => !empty($data["suppliers_id"]) ? Dropdown::getDropdownName("glpi_suppliers", $data["suppliers_id"]) : '',
                'class' => 'center',
            ];
            $cells[] = [
                'value' => !empty($data["buy_date"]) ? Html::convDate($data["buy_date"]) : '',
                'class' => 'center',
            ];
        }

        $rows[] = ['class' => 'tab_bg_1', 'cells' => $cells];
    }

    return $rows;
}
