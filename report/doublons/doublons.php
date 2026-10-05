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

global $DB;

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;

Session::checkRight("plugin_reports_doublons", READ);

$computer = new Computer();
$computer->checkGlobal(READ);

$dbu      = new DbUtils();

//TRANS: The name of the report = Duplicate computers
Html::header(__('Duplicate computers', 'reports'), '', "utils", "report");

Report::title();

$crits = [0 => Dropdown::EMPTY_VALUE,
    1 => __('Name'),
    2 => __('Model') . " + " . __('Serial number'),
    3 => __('Name') . " + " . __('Model') . " + " . __('Serial number'),
    4 => __('MAC address'),
    5 => __('IP address'),
    6 => __('Inventory number')];

// $crit is a numeric report selector; cast to int at every source so a value like
// "1&foo=bar" cannot survive the "$crit > 0" gate and reach the query below.
if (isset($_GET["crit"])) {
    $crit = (int) $_GET["crit"];

} elseif (isset($_POST["crit"])) {
    $crit = (int) $_POST["crit"];

} elseif (isset($_SESSION['plugin_reports_doublons_crit'])) {
    $crit = (int) $_SESSION['plugin_reports_doublons_crit'];

} else {
    $crit = 0;
}
// ---------- Form ------------
// A save button of the core used to be built here through TemplateRenderer::render(), whose
// return value was never echoed: it never appeared. It could not have worked anyway, its
// script (js/modules/Search/GenericView.js) being loaded on the search pages only. The dead
// call is dropped rather than turned into a button that does nothing.
$form = [
    'action'     => $_SERVER["REQUEST_URI"],
    'title'      => __('Duplicate computers', 'reports'),
    'nb_columns' => 2,
    'rows'       => [[
        'cells' => [[
            'name'  => 'crit',
            'label' => _n('Criterion', 'Criteria', 2),
            'field' => Dropdown::showFromArray('crit', $crits, ['value' => $crit, 'display' => false]),
        ]],
    ]],
    'submit'     => ['name' => 'search', 'value' => 'valider', 'label' => __('Search')],
];

if ($crit == 5) { // Search Duplicate IP Address - From glpi_networking_ports
    $IPBlacklist = "A_ipa.`name` != ''
                   AND A_ipa.`name` != '0.0.0.0'";

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::IP]]);

    foreach ($query as $data) {
        // This fragment feeds a QueryExpression, so escape the blacklist value
        // with the DB layer ($DB::quoteValue already returns a quoted literal)
        // instead of the unreliable addslashes().
        if (strpos($data["value"], '%')) {
            $IPBlacklist .= " AND A_ipa.`name` NOT LIKE " . $DB::quoteValue($data["value"]);
        } else {
            $IPBlacklist .= " AND B_ipa.`name` != " . $DB::quoteValue($data["value"]);
        }
    }
    //
    //    $criteria = "SELECT A.`id` AS AID,
    //                  A.`name` AS Aname,
    //                  A_ipa.`name` AS Aaddr,
    //                  A.`entities_id` AS entity,
    //
    //                  B.`id` AS BID,
    //                  B.`name` AS Bname,
    //                  B_ipa.`name` AS Baddr
    //
    //            FROM `glpi_computers` A
    //            LEFT JOIN `glpi_networkports` A_np
    //               ON  A_np.`itemtype` = 'Computer'
    //               AND A_np.`items_id` = A.`id`
    //            LEFT JOIN `glpi_networknames` A_nn
    //               ON  A_nn.`itemtype` = 'NetworkPort'
    //               AND A_nn.`items_id` = A_np.`id`
    //            LEFT JOIN `glpi_ipaddresses`  A_ipa
    //               ON  A_ipa.`itemtype` = 'NetworkName'
    //               AND A_ipa.`items_id` = A_nn.`id`
    //
    //
    //            LEFT JOIN `glpi_computers` B
    //               ON B.`id` > A.`id`
    //               AND A.`entities_id` = B.`entities_id`
    //            LEFT JOIN `glpi_networkports` B_np
    //               ON  B_np.`itemtype` = 'Computer'
    //               AND B_np.`items_id` = B.`id`
    //            LEFT JOIN `glpi_networknames` B_nn
    //               ON  B_nn.`itemtype` = 'NetworkPort'
    //               AND B_nn.`items_id` = B_np.`id`
    //            LEFT JOIN `glpi_ipaddresses`  B_ipa
    //               ON  B_ipa.`itemtype` = 'NetworkName'
    //               AND B_ipa.`items_id` = B_nn.`id`
    //
    //            " . $dbu->getEntitiesRestrictRequest(" WHERE ", "A", "entities_id") . "
    //                 AND ($IPBlacklist)
    //                 AND A.`is_template` = '0'
    //                 AND B.`is_template` = '0'
    //                 AND A.`is_deleted` = '0'
    //                 AND B.`is_deleted` = '0'
    //                 AND A_ipa.`name` = B_ipa.`name`";
    //
    //

    $criteria = [

        'SELECT' => [
            'A.id AS AID',
            'A.name AS Aname',
            'A_ipa.name AS Aaddr',
            'A.entities_id AS entity',
            'B.id AS BID',
            'B.name AS Bname',
            'B_ipa.name AS Baddr',
        ],
        'FROM' => 'glpi_computers AS A',
        'LEFT JOIN' => [
            // --- A side ---
            'glpi_networkports AS A_np' => [
                'ON' => [
                    'A_np'   => 'items_id',
                    'A'      => 'id', [
                        'AND' => [
                            'A_np.itemtype' => 'Computer',
                        ],
                    ],
                ],
            ],
            'glpi_networknames AS A_nn' => [
                'ON' => [
                    'A_nn'   => 'items_id',
                    'A_np'      => 'id', [
                        'AND' => [
                            'A_nn.itemtype' => 'NetworkPort',
                        ],
                    ],
                ],
            ],
            'glpi_ipaddresses AS A_ipa' => [
                'ON' => [
                    'A_ipa'   => 'items_id',
                    'A_nn'      => 'id', [
                        'AND' => [
                            'A_ipa.itemtype' => 'NetworkName',
                        ],
                    ],
                ],
            ],
            // --- B computers ---
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id',
                ],
            ],
            'glpi_networkports AS B_np' => [
                'ON' => [
                    'B_np'   => 'items_id',
                    'B'      => 'id', [
                        'AND' => [
                            'B_np.itemtype' => 'Computer',
                        ],
                    ],
                ],
            ],
            'glpi_networknames AS B_nn' => [
                'ON' => [
                    'B_nn'   => 'items_id',
                    'B_np'      => 'id', [
                        'AND' => [
                            'B_nn.itemtype' => 'NetworkPort',
                        ],
                    ],
                ],
            ],
            'glpi_ipaddresses AS B_ipa' => [
                'ON' => [
                    'B_ipa'   => 'items_id',
                    'B_nn'      => 'id', [
                        'AND' => [
                            'B_ipa.itemtype' => 'NetworkName',
                        ],
                    ],
                ],
            ],
        ],
        'WHERE' => [
            new QueryExpression('B.id > A.id'),
            // blacklist IP
            new QueryExpression("($IPBlacklist)"),
            // filtres
            'A.is_template' => 0,
            'B.is_template' => 0,
            'A.is_deleted'  => 0,
            'B.is_deleted'  => 0,
            // IP identiques
            new QueryExpression('A_ipa.name = B_ipa.name'),
        ],
    ];

    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        'A',
    );

    $col = __('IP');

} elseif ($crit == 4) { // Search Duplicate Mac Address - From glpi_computer_device

    $MacBlacklist = [];

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::MAC]]);

    foreach ($query as $data) {
        // Passed as an array criterion ('NOT IN', $MacBlacklist) below, which the
        // DB layer quotes on its own: no manual escaping (addslashes would
        // double-escape values containing quotes).
        $MacBlacklist [] = $data["value"];
    }

    if (empty($MacBlacklist)) {
        $MacBlacklist[] = '44:45:53:54:42:00';
        $MacBlacklist[] = 'BA:D0:BE:EF:FA:CE';
        $MacBlacklist[] = '00:53:45:00:00:00';
        $MacBlacklist[] = '80:00:60:0F:E8:00';
    }
    //    $Sql = "SELECT A.`id` AS AID,
    //                  A.`name` AS Aname,
    //                  A_np.`mac` AS Aaddr,
    //                  A.`entities_id` AS entity,
    //                  B.`id` AS BID,
    //                  B.`name` AS Bname,
    //                  B_np.`mac` AS Baddr
    //
    //           FROM `glpi_computers` A
    //           LEFT JOIN `glpi_networkports` A_np
    //              ON  A_np.`itemtype` = 'Computer'
    //              AND A_np.`items_id` = A.`id`
    //
    //           LEFT JOIN `glpi_computers` B
    //              ON B.`id` > A.`id`
    //              AND A.`entities_id` = B.`entities_id`
    //            LEFT JOIN `glpi_networkports` B_np
    //               ON  B_np.`itemtype` = 'Computer'
    //               AND B_np.`items_id` = B.`id`
    //
    //            " . $dbu->getEntitiesRestrictRequest(" WHERE ", "A", "entities_id") . "
    //                 AND A_np.`mac` = B_np.`mac`
    //                 AND A_np.`mac` NOT IN ($MacBlacklist)
    //                 AND A.`is_template` = '0'
    //                 AND B.`is_template` = '0'
    //                 AND A.`is_deleted` = '0'
    //                 AND B.`is_deleted` = '0'";

    $criteria = [
        'SELECT' => [
            'A.id AS AID',
            'A.name AS Aname',
            'A_np.mac AS Aaddr',
            'A.entities_id AS entity',
            'B.id AS BID',
            'B.name AS Bname',
            'B_np.mac AS Baddr',
        ],
        'FROM' => 'glpi_computers AS A',
        'LEFT JOIN' => [
            // --- A side ---
            'glpi_networkports AS A_np' => [
                'ON' => [
                    'A_np'   => 'items_id',
                    'A'      => 'id', [
                        'AND' => [
                            'A_np.itemtype' => 'Computer',
                        ],
                    ],
                ],
            ],
            // --- B computers ---
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id',
                ],
            ],
            'glpi_networkports AS B_np' => [
                'ON' => [
                    'B_np'   => 'items_id',
                    'B'      => 'id', [
                        'AND' => [
                            'B_np.itemtype' => 'Computer',
                        ],
                    ],
                ],
            ],
        ],
        'WHERE' => [
            new QueryExpression('B.id > A.id'),
            // même MAC
            new QueryExpression('A_np.mac = B_np.mac'),
            // blacklist
            ['A_np.mac' => ['NOT IN', $MacBlacklist]],
            // filtres
            'A.is_template' => 0,
            'B.is_template' => 0,
            'A.is_deleted'  => 0,
            'B.is_deleted'  => 0,
        ],
    ];

    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        'A',
    );

    $col = __('MAC');

} elseif ($crit > 0) { // Search Duplicate Name and/ord Serial or Otherserial - From glpi_computers
    $SerialBlacklist = [];

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::SERIAL]]);
    foreach ($query as $data) {
        // Passed as an array criterion ('NOT IN', $SerialBlacklist) below, which
        // the DB layer quotes on its own: no manual escaping (addslashes would
        // double-escape values containing quotes).
        $SerialBlacklist[] = $data["value"];
    }
    //
    //    $Sql = "SELECT A.`id` AS AID, A.`name` AS Aname,
    //                  A.`entities_id` AS entity,
    //                  B.`id` AS BID, B.`name` AS Bname
    //           FROM `glpi_computers` A,
    //                `glpi_computers` B "
    //            . $dbu->getEntitiesRestrictRequest(" WHERE ", "A", "entities_id") . "
    //                 AND B.`id` > A.`id`
    //                 AND A.`entities_id` = B.`entities_id`
    //                 AND A.`is_template` = '0'
    //                 AND B.`is_template` = '0'
    //                 AND A.`is_deleted` = '0'
    //                 AND B.`is_deleted` = '0'";
    //
    //    if ($crit == 6) {
    //        $Sql .= " AND A.`otherserial` != ''
    //                AND A.`otherserial` = B.`otherserial`";
    //    } else {
    //        if ($crit & 1) {
    //            $Sql .= " AND A.`name` != ''
    //                   AND A.`name` = B.`name`";
    //        }
    //        if ($crit & 2) {
    //            $Sql .= " AND A.`serial` NOT IN ($SerialBlacklist)
    //                   AND A.`serial` = B.`serial`
    //                   AND A.`computermodels_id` = B.`computermodels_id`";
    //        }
    //    }

    $criteria = [
        'SELECT' => [
            'A.id AS AID',
            'A.name AS Aname',
            'A.entities_id AS entity',
            'B.id AS BID',
            'B.name AS Bname',
        ],
        'FROM' => 'glpi_computers AS A',
        'LEFT JOIN'       => [
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id',
                ],
            ],
        ],
        'WHERE' => // filtres communs
            ['A.is_template' => 0,
                'B.is_template' => 0,
                'A.is_deleted'  => 0,
                'B.is_deleted'  => 0,
                new QueryExpression('B.id > A.id'),
            ],
    ];

    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        'A',
    );

    if ($crit == 6) {

        $criteria['WHERE'][] = new QueryExpression("A.otherserial <> ''");
        $criteria['WHERE'][] = new QueryExpression("A.otherserial = B.otherserial");

    } else {

        if ($crit & 1) {
            $criteria['WHERE'][] = new QueryExpression("A.name <> ''");
            $criteria['WHERE'][] = new QueryExpression("A.name = B.name");
        }

        if ($crit & 2) {
            $criteria['WHERE'][] = new QueryExpression("A.serial <> ''");
            $criteria['WHERE'][] = ['A.serial' => ['NOT IN', $SerialBlacklist]];
            $criteria['WHERE'][] = new QueryExpression("A.serial = B.serial");
            $criteria['WHERE'][] = new QueryExpression("A.computermodels_id = B.computermodels_id");
        }
    }

    $col = "";
}


$page = ['form' => $form];

if ($crit > 0) { // Display result
    $canedit = $computer->canUpdate();
    $colspan = ($col ? 8 : 7) + ($canedit ? 1 : 0);

    // save crit for massive action
    $_SESSION['plugin_reports_doublons_crit'] = $crit;

    $header_cells = [];
    foreach (['', 'blue'] as $class) {
        if ($canedit) {
            $header_cells[] = ['value' => '', 'class' => $class];
        }
        $titles = [__('ID'), __('Name'), __('Manufacturer'), __('Model'), __('Serial number'),
            __('Inventory number')];
        if ($col) {
            $titles[] = $col;
        }
        $titles[] = __('Last inventory date', 'reports');
        foreach ($titles as $title) {
            $header_cells[] = ['value' => $title, 'class' => $class];
        }
    }
    $table = [
        'class'       => 'tab_cadre_fixe',
        'header_rows' => [
            ['cells' => [
                ['value' => __('First computer', 'reports'), 'colspan' => $colspan],
                ['value' => __('Second computer', 'reports'), 'colspan' => $colspan, 'class' => 'blue'],
            ]],
            ['cells' => $header_cells],
        ],
        'rows'        => [],
    ];
    $colspan *= 2;

    $comp = new Computer();
    $ids  = [];

    // One side of a duplicate pair: checkbox, identifier, link and inventory columns
    $build_side = static function (array $data, string $id_key, string $name_key, string $addr_key, string $class) use ($comp, $canedit, $col, &$ids): array {
        $cells = [];
        if ($canedit) {
            if (isset($ids[$data[$id_key]])) {
                $cells[] = ['value' => '', 'class' => $class];
            } else {
                $ids[$data[$id_key]] = true;
                $cells[] = ['html' => Html::getMassiveActionCheckBox('Computer', $data[$id_key]), 'class' => $class];
            }
        }
        $cells[] = ['value' => (int) $data[$id_key], 'class' => trim('b ' . $class)];
        if ($comp->getFromDB($data[$id_key])) {
            // Security (stored XSS): since GLPI 10 the dropdown labels are stored raw and
            // Dropdown::getDropdownName() returns them as they are. Every value below is escaped
            // by the template; the labels are writable by anyone holding the dropdown right, and
            // are also fed by the inventory agents and the datainjection/API imports.
            $cells[] = ['html' => $comp->getLink(), 'class' => $class];
            $cells[] = ['value' => Dropdown::getDropdownName("glpi_manufacturers", $comp->getField('manufacturers_id')), 'class' => $class];
            $cells[] = ['value' => Dropdown::getDropdownName("glpi_computermodels", $comp->getField('computermodels_id')), 'class' => $class];
            $cells[] = ['value' => $comp->getField('serial'), 'class' => $class];
            $cells[] = ['value' => $comp->getField('otherserial'), 'class' => $class];
        } else {
            // The second side used to print the name of the FIRST computer ($data["Aname"])
            // when the second one could not be loaded.
            $cells[] = ['value' => $data[$name_key], 'colspan' => 5, 'class' => $class];
        }
        if ($col) {
            $cells[] = ['value' => $data[$addr_key], 'class' => $class];
        }
        $cells[] = ['value' => (string) getLastInventory($data[$id_key]), 'class' => $class];

        return $cells;
    };

    $iterator = $DB->request($criteria);

    $i = 0;
    $prev = -1;
    foreach ($iterator as $data) {
        $i++;
        if ($prev != $data["entity"]) {
            $prev = $data["entity"];
            $table['rows'][] = [
                'class' => 'tab_bg_4',
                'cells' => [[
                    'value'   => Dropdown::getDropdownName("glpi_entities", $prev),
                    'class'   => 'center',
                    'colspan' => $colspan,
                ]],
            ];
        }
        $table['rows'][] = [
            'class' => 'tab_bg_2',
            'cells' => array_merge(
                $build_side($data, 'AID', 'Aname', 'Aaddr', ''),
                $build_side($data, 'BID', 'Bname', 'Baddr', 'blue'),
            ),
        ];
    }
    $table['rows'][] = [
        'class' => 'tab_bg_4',
        'cells' => [[
            'html'    => TemplateRenderer::getInstance()->render('@reports/report/message.html.twig', [
                'type' => 'danger',
                'text' => $i
                    ? sprintf(__('%1$s: %2$s'), __('Duplicate computers', 'reports'), $i)
                    : __('No results found'),
            ]),
            'class'   => 'center',
            'colspan' => $colspan,
        ]],
    ];

    $page['tables'] = [$table];
    if ($canedit) {
        $page['massive'] = [
            'form_id' => 'massformComputer',
            'top'     => '',
            'bottom'  => $i ? Html::showMassiveActions([
                'num_displayed' => $i,
                'container'     => 'massformComputer',
                'ontop'         => false,
                'forcecreate'   => true,
                'display'       => false,
            ]) : '',
        ];
    }
}

TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', $page);

Html::footer();


// buildBookmarkUrl() stood here. Its only caller was the REQUEST_URI write removed above, and
// the URL it built was wrong in its own right: it appended "?crit=<n>" to a REQUEST_URI that
// already carried a query string, producing a second "?". It goes with its caller.


function getLastInventory($computers_id)
{
    global $DB;

    // check OCS install
    $plugin        = new Plugin();
    $ocs_installed = $plugin->isInstalled('ocsinventoryng');


    if ($ocs_installed && $DB->tableExists('glpi_plugin_ocsinventoryng_ocslinks')) {
        $table = 'glpi_plugin_ocsinventoryng_ocslinks';
        $field = 'last_ocs_update';
    } else {
        $table = 'glpi_computers';
        $field = 'last_inventory_update';
    }

    $query = $DB->request(['SELECT' => $field,
        'FROM' => $table,
        'WHERE'  => [($table === 'glpi_computers' ? 'id' : 'computers_id') => $computers_id]]);

    if (count($query) > 0) {
        foreach ($query as $id => $row) {
            return $row[$field];
        }
    }

    return '';
}
