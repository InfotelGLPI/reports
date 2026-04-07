<?php

/**
 -------------------------------------------------------------------------
  LICENSE

 This file is part of Reports plugin for GLPI.

 Reports is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or
 (at your option) any later version.

 Reports is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with Reports. If not, see <http://www.gnu.org/licenses/>.

 @package   reports
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

global $DB;

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;

Session::checkRight("plugin_reports_doublons", READ);

$computer = new Computer();
$computer->checkGlobal(READ);

$dbu      = new DbUtils();

//TRANS: The name of the report = Duplicate computers
Html::header(__('Duplicate computers', 'report'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

$crits = [0 => Dropdown::EMPTY_VALUE,
    1 => __('Name'),
    2 => __('Model') . " + " . __('Serial number'),
    3 => __('Name') . " + " . __('Model') . " + " . __('Serial number'),
    4 => __('MAC address'),
    5 => __('IP address'),
    6 => __('Inventory number')];

if (isset($_GET["crit"])) {
    $crit = $_GET["crit"];

} elseif (isset($_POST["crit"])) {
    $crit = $_POST["crit"];

} elseif (isset($_SESSION['plugin_reports_doublons_crit'])) {
    $crit = $_SESSION['plugin_reports_doublons_crit'];

} else {
    $crit = 0;
}
$rand  = mt_rand();

// ---------- Form ------------
echo "<form action='" . $_SERVER["REQUEST_URI"] . "' method='post'>";
echo "<table class='tab_cadre' cellpadding='5'>\n";
echo "<tr class='tab_bg_1 center'>";
echo "<th colspan='3'>" . __('Duplicate computers', 'reports') . "</th></tr>\n";

echo "<tr class='tab_bg_1'><td class='right'>" . _n('Criterion', 'Criteria', 2) . "</td><td>";

Dropdown::showFromArray(
    'crit',
    $crits,
    ['value' => $crit]
);

echo "</td>";

if ($crit > 0) {
    echo "<td>";
    //Add parameters to uri to be saved as bookmarks
    $_SERVER["REQUEST_URI"] = buildBookmarkUrl($_SERVER["REQUEST_URI"], $crit);
    //   SavedSearch::showSaveButton(SavedSearch::SEARCH,'Computer');
    TemplateRenderer::getInstance()->render('pages/tools/savedsearch/save_button.html.twig', [
        'type' => SavedSearch::SEARCH,
        'itemtype' => 'Computer',
    ]);
    echo "</td>";
}
echo"</tr>\n";

echo "<tr class='tab_bg_1 center'><td colspan='" . (($crit > 0) ? '3' : '2') . "'>";
echo Html::submit(__('Search'), ['value' => 'valider', 'class' => 'btn btn-primary']);
echo "</td></tr>\n";
echo "</table>\n";
Html::closeForm();

if ($crit == 5) { // Search Duplicate IP Address - From glpi_networking_ports
    $IPBlacklist = "A_ipa.`name` != ''
                   AND A_ipa.`name` != '0.0.0.0'";

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::IP]]);

    foreach ($query as $data) {
        if (strpos($data["value"], '%')) {
            $IPBlacklist .= " AND A_ipa.`name` NOT LIKE '" . addslashes($data["value"]) . "'";
        } else {
            $IPBlacklist .= " AND B_ipa.`name` != '" . addslashes($data["value"]) . "'";
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
            'B_ipa.name AS Baddr'
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
                ]
            ],
            'glpi_networknames AS A_nn' => [
                'ON' => [
                    'A_nn'   => 'items_id',
                    'A_np'      => 'id', [
                        'AND' => [
                            'A_nn.itemtype' => 'NetworkPort',
                        ],
                    ],
                ]
            ],
            'glpi_ipaddresses AS A_ipa' => [
                'ON' => [
                    'A_ipa'   => 'items_id',
                    'A_nn'      => 'id', [
                        'AND' => [
                            'A_ipa.itemtype' => 'NetworkName',
                        ],
                    ],
                ]
            ],
            // --- B computers ---
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id'
                ]
            ],
            'glpi_networkports AS B_np' => [
                'ON' => [
                    'B_np'   => 'items_id',
                    'B'      => 'id', [
                        'AND' => [
                            'B_np.itemtype' => 'Computer',
                        ],
                    ],
                ]
            ],
            'glpi_networknames AS B_nn' => [
                'ON' => [
                    'B_nn'   => 'items_id',
                    'B_np'      => 'id', [
                        'AND' => [
                            'B_nn.itemtype' => 'NetworkPort',
                        ],
                    ],
                ]
            ],
            'glpi_ipaddresses AS B_ipa' => [
                'ON' => [
                    'B_ipa'   => 'items_id',
                    'B_nn'      => 'id', [
                        'AND' => [
                            'B_ipa.itemtype' => 'NetworkName',
                        ],
                    ],
                ]
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
            new QueryExpression('A_ipa.name = B_ipa.name')
        ]
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'A'
        );

    $col = __('IP');

} elseif ($crit == 4) { // Search Duplicate Mac Address - From glpi_computer_device

    $MacBlacklist = [];

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::MAC]]);

    foreach ($query as $data) {
        $MacBlacklist []= addslashes($data["value"]);
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
            'B_np.mac AS Baddr'
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
                ]
            ],
            // --- B computers ---
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id'
                ]
            ],
            'glpi_networkports AS B_np' => [
                'ON' => [
                    'B_np'   => 'items_id',
                    'B'      => 'id', [
                        'AND' => [
                            'B_np.itemtype' => 'Computer',
                        ],
                    ],
                ]
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
            'B.is_deleted'  => 0
        ]
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'A'
        );

    $col = __('MAC');

} elseif ($crit > 0) { // Search Duplicate Name and/ord Serial or Otherserial - From glpi_computers
    $SerialBlacklist = [];

    $query = $DB->request(['SELECT' => 'value',
        'FROM' => 'glpi_blacklists',
        'WHERE'  => ['type' => Blacklist::SERIAL]]);
    foreach ($query as $data) {
        $SerialBlacklist[] = addslashes($data["value"]);
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
            'B.name AS Bname'
        ],
        'FROM' => 'glpi_computers AS A',
        'LEFT JOIN'       => [
            'glpi_computers AS B' => [
                'ON' => [
                    'A' => 'entities_id',
                    'B' => 'entities_id'
                ]
            ]
        ],
        'WHERE' => // filtres communs
            ['A.is_template' => 0,
            'B.is_template' => 0,
            'A.is_deleted'  => 0,
            'B.is_deleted'  => 0,
                new QueryExpression('B.id > A.id'),
        ]
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'A'
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


if ($crit > 0) { // Display result
    $canedit = $computer->canUpdate();
    $colspan = ($col ? 8 : 7) + ($canedit ? 1 : 0);

    // save crit for massive action
    $_SESSION['plugin_reports_doublons_crit'] = $crit;

    $rand = mt_rand();
    if ($canedit) {
        Html::openMassiveActionsForm('massformComputer');
    }
    echo "<br><table class='tab_cadre_fixe' cellpadding='5'>"
       . "<tr><th colspan='$colspan'>" . __('First computer', 'reports') . "</th>"
       . "<th class='blue' colspan='$colspan'>" . __('Second computer', 'reports') . "</th></tr>\n"
       . "<tr>";
    $colspan *= 2;

    if ($canedit) {
        echo "<th>&nbsp;</th>";
    }
    echo "<th>" . __('ID') . "</th>"
       . "<th>" . __('Name') . "</th>"
       . "<th>" . __('Manufacturer') . "</th>"
       . "<th>" . __('Model') . "</th>"
       . "<th>" . __('Serial number') . "</th>"
       . "<th>" . __('Inventory number') . "</th>";
    if ($col) {
        echo "<th>$col</th>";
    }
    echo "<th>" . __('Last inventory date', 'reports') . "</th>";

    if ($canedit) {
        echo "<th>&nbsp;</th>";
    }

    echo "<th class='blue'>" . __('ID') . "</th>"
         . "<th class='blue'>" . __('Name') . "</th>"
         . "<th class='blue'>" . __('Manufacturer') . "</th>"
         . "<th class='blue'>" . __('Inventory number') . "</th>"
         . "<th class='blue'>" . __('Serial number') . "</th>"
         . "<th class='blue'>" . __('Inventory number') . "</th>";
    if ($col) {
        echo "<th class='blue'>$col</th>";
    }
    echo "<th class='blue'>" . __('Last inventory date', 'reports') . "</th>";

    echo "</tr>\n";


    $comp = new Computer();
    $ids  = [];

    $iterator = $DB->request($criteria);
//    for ($prev = -1, $i = 0 ; $data = $DBread->fetchArray($result) ; $i++) {

    $i = 0;
    $prev = -1;
    foreach ($iterator as $data) {
        $i++;
        if ($prev != $data["entity"]) {
            $prev = $data["entity"];
            echo "<tr class='tab_bg_4'><td class='center' colspan='$colspan'>"
               . Dropdown::getDropdownName("glpi_entities", $prev) . "</td></tr>\n";
        }
        echo "<tr class='tab_bg_2'>";
        if ($canedit) {
            if (isset($ids[$data["AID"]])) {
                echo "<td>&nbsp;</td>";
            } else {
                $ids[$data["AID"]] = true;
                echo "<td>" . Html::getMassiveActionCheckBox('Computer', $data["AID"]) . "</td>";
            }
        }
        echo "<td class='b'>" . $data["AID"] . "</td>";
        if ($comp->getFromDB($data["AID"])) {

            echo "<td>";
            echo $comp->getLink();
            echo "</td><td>";
            echo Dropdown::getDropdownName("glpi_manufacturers", $comp->getField('manufacturers_id'));
            echo "</td><td>";
            echo Dropdown::getDropdownName("glpi_computermodels", $comp->getField('computermodels_id'));
            echo "</td><td>" . $comp->getField('serial');
            echo "</td><td>" . $comp->getField('otherserial') . "</td>";

        } else {
            echo "<td colspan='5'>" . $data["Aname"] . "</td>";
        }
        if ($col) {
            echo "<td>" . $data["Aaddr"] . "</td>";
        }
        echo "<td>";
        echo getLastInventory($data['AID']);
        echo "</td>";
        if ($canedit) {
            if (isset($ids[$data["BID"]])) {
                echo "<td>&nbsp;</td>";
            } else {
                $ids[$data["BID"]] = true;
                echo "<td>" . Html::getMassiveActionCheckBox('Computer', $data["BID"]) . "</td>";
            }
        }
        echo "<td class='b blue'>" . $data["BID"] . "</td>";
        if ($comp->getFromDB($data["BID"])) {
            echo "<td class='blue'>";
            echo $comp->getLink();
            echo "</td><td class='blue'>";
            echo Dropdown::getDropdownName("glpi_manufacturers", $comp->getField('manufacturers_id'));
            echo "</td><td class='blue'>";
            echo Dropdown::getDropdownName("glpi_computermodels", $comp->getField('computermodels_id'));
            echo "</td><td class='blue'>" . $comp->getField('serial');
            echo "</td><td class='blue'>" . $comp->getField('otherserial') . "</td>";
        } else {
            echo "<td colspan='5' class='blue'>" . $data["Aname"] . "</td>";
        }
        if ($col) {
            echo "<td class='blue'>" . $data["Baddr"] . "</td>";
        }
        echo "<td class='blue'>";
        echo getLastInventory($data['BID']);
        echo "</td>";

        echo "</tr>\n";
    }
    echo "<tr class='tab_bg_4'><td class='center' colspan='$colspan'>";
    if ($i) {
        echo "<div class='alert alert-danger center'>";
        printf(__('%1$s: %2$s'), __('Duplicate computers', 'reports'), $i);
        echo "</div>";
    }  else {
        echo "<div class='alert alert-danger center'>";
        echo __s('No results found');
        echo "</div>";
    }
    echo "</td></tr>\n";
    echo "</table>";
    if ($canedit) {
        if ($i) {
            $massiveactionparams = ['num_displayed'    => $i,
                'container'        => 'massformComputer',
                'ontop'            => false,
                'forcecreate'      => true];
            Html::showMassiveActions($massiveactionparams);
        }
        Html::closeForm();
    }
}
Html::footer();


function buildBookmarkUrl($url, $crit)
{
    return $url . "?crit=" . $crit;
}


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
        'WHERE'  => ['computers_id' => $computers_id]]);

    if (count($query) > 0) {
        foreach ($query as $id => $row) {
            return $row[$field];
        }
    }

    return '';
}
