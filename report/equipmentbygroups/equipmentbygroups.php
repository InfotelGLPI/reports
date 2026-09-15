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

displaySearchForm($params);

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
        echo "<br><table class='tab_cadre' cellpadding='5'>";
        echo "<tr><th>" . sprintf(__('%1$s: %2$s'), __('Group'), htmlescape($datas['name'])) . "</th></th></tr>";
        $last_group_id = $datas["id"];
        echo "</table>";
    }

    getObjectsByGroupAndEntity($datas["id"], $_SESSION["glpiactive_entity"]);
}

Html::footer();


/**
 * Display group form
 **/
/**
 * @param array<string, mixed> $params Merged request values of the report
 **/
function displaySearchForm(array $params)
{
    global $CFG_GLPI;

    echo "<form action='" . htmlescape($_SERVER["REQUEST_URI"]) . "' method='post'>";
    echo "<table class='tab_cadre' cellpadding='5'>";
    echo "<tr class='tab_bg_1 center'>";
    echo "<td width='300'>";
    echo __('Group') . "&nbsp;&nbsp;";
    Group::dropdown([
        'name' => "groups_id",
        'value' => (int) $params["groups_id"],
        'entity' => $_SESSION["glpiactive_entity"],
        'condition' => ['is_itemgroup' => 1],
    ]);
    echo "</td>";

    // Display Reset search
    echo "<td>";
    echo "<a href='" . $CFG_GLPI['root_doc'] . "/plugins/reports/report/equipmentbygroups/equipmentbygroups.php?reset_search=reset_search'>"
        . "<img title='" . __s('Blank') . "' alt='" . __s('Blank') . "' src='"
        . $CFG_GLPI["root_doc"] . "/pics/reset.png' class='calendrier'></a>";
    echo "</td>";

    echo "<td>";
    echo Html::submit(_x('button', 'Post'), ['value' => 'Valider', 'class' => 'btn btn-primary']);
    echo "</td>";

    echo "</tr></table>";
    Html::closeForm();
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
 * Display all devices by group
 *
 * @param $group_id - the group ID
 * @param $entity - the current entity
 **/
function getObjectsByGroupAndEntity($group_id, $entity)
{
    global $DB, $CFG_GLPI;

    $display_header = false;

    // Two problems in the original loop. It removed entries from $CFG_GLPI['asset_types']
    // while walking it, which mutated the global list for the rest of the request, and it
    // had no continue, so Certificate and SoftwareLicense were queried all the same. And no
    // read right was ever confronted: the only guards on this report are the plugin right
    // and the active entity, so a profile with no right on Printer, NetworkEquipment, PDU,
    // Enclosure or a custom asset still received their name, serial number, inventory and
    // immobilisation numbers, supplier and purchase date. report/pcsbyentity already filters
    // its type list with canView() for exactly that reason.
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
                'immo_number',
                'suppliers_id',
                'buy_date',
            ],
            'FROM' => $item->getTable(),
            'LEFT JOIN' => [
                'glpi_infocoms' => [
                    'FKEY' => [
                        $item->getTable() => 'id',
                        'glpi_infocoms' => 'items_id',
                    ],
                    ['glpi_infocoms.itemtype' => $itemtype],
                ],
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
            if (!$display_header) {
                echo "<br><table class='tab_cadre_fixehov'>";
                echo "<tr><th>" . __('Type') . "</th><th>" . __('Name') . "</th>";
                echo "<th>" . __('Serial number') . "</th><th>" . __('Inventory number') . "</th>";
                echo "<th>" . __('Immobilization number') . "</th>";
                echo "<th>" . __('Supplier') . "</th><th>" . __('Date of purchase') . "</th>";
                echo "</tr>";
                $display_header = true;
            }
            displayUserDevices($itemtype, $iterator);
        }

    }
    echo "</table>";
}


/**
 * Display all device for a group
 *
 * @param $type - the objet type
 * @param $result - the resultset of all the devices found
 **/
function displayUserDevices($type, $result)
{
    global $CFG_GLPI;

    $item = new $type();
    foreach ($result as $data) {
        $link = htmlescape($data["name"]);
        $url = Toolbox::getItemTypeFormURL("$type");
        $link = "<a href='" . $url . "?id=" . (int) $data["id"] . "'>" . $link
            . (($CFG_GLPI["is_ids_visible"] || empty($link)) ? " (" . (int) $data["groups_id"] . ")" : "")
            . "</a>";
        $linktype = "";
        if (isset($groups[$data["id"]])) {
            $linktype = sprintf(__('%1$s %2$s'), __('Group'), $groups[$data["groups_id"]]);
        }

        echo "<tr class='tab_bg_1'><td class='center'>" . $item->getTypeName() . "</td>"
            . "<td class='center'>$link</td>";

        echo "<td class='center'>";
        if (isset($data["serial"]) && !empty($data["serial"])) {
            echo htmlescape($data["serial"]);
        } else {
            echo '&nbsp;';
        }
        echo "</td><td class='center'>";

        if (isset($data["otherserial"]) && !empty($data["otherserial"])) {
            echo htmlescape($data["otherserial"]);
        } else {
            echo '&nbsp;';
        }
        echo "</td><td class='center'>";

        if (isset($data["immo_number"]) && !empty($data["immo_number"])) {
            echo htmlescape($data["immo_number"]);
        } else {
            echo '&nbsp;';
        }
        echo "</td><td class='center'>";

        if (isset($data["suppliers_id"]) && !empty($data["suppliers_id"])) {
            // Security (stored XSS): Dropdown::getDropdownName() returns the label exactly as it
            // is stored, and a supplier name is writable by any holder of the dropdown right or
            // by an import. The immo_number cell just above escapes for that reason; this one
            // did not.
            echo htmlescape(Dropdown::getDropdownName("glpi_suppliers", $data["suppliers_id"]));
        } else {
            echo '&nbsp;';
        }
        echo "</td><td class='center'>";

        if (isset($data["buy_date"]) && !empty($data["buy_date"])) {
            echo Html::convDate($data["buy_date"]);
        } else {
            echo '&nbsp;';
        }
        echo "</td></tr>";
    }
}
