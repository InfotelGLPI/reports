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
 @authors    Nelly Mahu-Lasson, Remi Collet, Benoit Machiavello
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

use Glpi\DBAL\QueryFunction;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1; // Really a big SQL request

global $DB;

Session::checkRight("plugin_reports_histohard", READ);

$computer = new Computer();
$dbu      = new DbUtils();
$computer->checkGlobal(READ);

Html::header(__("History of last hardware's installations", 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

echo "<div class='center'>";
echo "<table class='tab_cadre_fixe'>\n";
echo "<tr class='tab_bg_1 center'>"
     . "<th colspan='5'>" . __("History of last hardware's installations", 'reports') . "</th></tr>\n";

echo "<tr><th>" . __('Date of inventory', 'reports') . "</th>"
      . "<th>" . __('User') . "</th>"
      . "<th>" . __('Network device') . "</th>"
      . "<th>" . __('Field') . "</th>"
      . "<th>" . __('Modification', 'reports') . "</th></tr>\n";

$criteria = [
    'SELECT' => ['glpi_logs.date_mod AS dat',
        'glpi_logs.linked_action',
        'glpi_logs.itemtype',
        'glpi_logs.itemtype_link',
        'glpi_logs.old_value',
        'glpi_logs.new_value',
        'glpi_computers.id AS cid',
        'glpi_computers.name',
        'glpi_logs.user_name',
        'glpi_logs.items_id',
        'glpi_computers.entities_id',
    ],
    'FROM' => 'glpi_logs',
    'LEFT JOIN'       => [
        'glpi_computers' => [
            'ON' => [
                'glpi_logs' => 'items_id',
                'glpi_computers'          => 'id',
            ],
        ],
    ],
    'WHERE' => [
        'linked_action' => [Log::HISTORY_CONNECT_DEVICE,
            Log::HISTORY_DISCONNECT_DEVICE,
            Log::HISTORY_DELETE_DEVICE,
            Log::HISTORY_UPDATE_DEVICE,
            Log::HISTORY_ADD_DEVICE],
        'itemtype' => 'Computer',
        'glpi_logs.date_mod' => ['>', QueryFunction::dateSub(
            date: QueryFunction::now(),
            interval: '21',
            interval_unit: 'DAY'
        )],
    ],
    'ORDERBY' => 'glpi_logs.id DESC',
    'LIMIT' => '0,100',
];

$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_computers'
);

$iterator = $DB->request($criteria);

$prev  = "";
$class = "tab_bg_2";
foreach ($iterator as $data) {
    if (empty($data["name"])) {
        $data["name"] = "(" . $data["cid"] . ")";
    }
    if ($prev == $data["dat"] . $data["name"]) {
        echo "</td></tr><tr class='" . $prevclass . " top'><td></td><td></td><td></td><td>";
    } else {
        if (!empty($prev)) {
            echo "</td></tr>\n";
        }
        $prev = $data["dat"] . $data["name"];
        echo "<tr class='" . $class . " top'><td>" . Html::convDateTime($data["dat"]) . "</td>"
              . "<td>" . $data["user_name"] . "&nbsp;</td>"
              . "<td><a href='" . Toolbox::getItemTypeFormURL('Computer') . "?id=" . $data["cid"] . "'>"
              . $data["name"] . "</a></td><td>";
        $prevclass = $class;
        $class = ($class == "tab_bg_2" ? "tab_bg_1" : "tab_bg_2");
    }
    $field = "";
    if ($data["linked_action"]) {
        $action_label = Log::getLinkedActionLabel($data["linked_action"]);
        // Yes it is an internal device
        switch ($data["linked_action"]) {
            case Log::HISTORY_ADD_DEVICE:
            case Log::HISTORY_CONNECT_DEVICE:
                $field = NOT_AVAILABLE;
                if ($item = $dbu->getItemForItemtype($data["itemtype_link"])) {
                    if ($item instanceof Item_Devices) {
                        $field = $item->getDeviceTypeName(1);
                    } else {
                        $field = $item->getTypeName(1);
                    }
                }
                $change = sprintf(__('%1$s: %2$s'), $action_label, $data[ "new_value"]);
                break;

            case Log::HISTORY_UPDATE_DEVICE:
                $field = NOT_AVAILABLE;
                $change = '';
                $linktype_field = explode('#', $data["itemtype_link"]);
                $linktype       = $linktype_field[0];
                $fieldval          = $linktype_field[1];
                $devicetype     = $linktype::getDeviceType();
                $field          = $devicetype;
                $specif_fields  = $linktype::getSpecificities();
                if (isset($specif_fields[$fieldval]['short name'])) {
                    $field   = $devicetype;
                    $field .= " (" . $specif_fields[$fieldval]['short name'] . ")";
                }
                //TRANS: %1$s is the old_value, %2$s is the new_value
                $change  = sprintf(
                    __('%1$s: %2$s'),
                    sprintf(__('%1$s (%2$s)'), $action_label, $field),
                    sprintf(__('%1$s by %2$s'), $data["old_value"], $data[ "new_value"])
                );
                break;

            case Log::HISTORY_DELETE_DEVICE:
            case Log::HISTORY_DISCONNECT_DEVICE:
                $field = NOT_AVAILABLE;
                if ($item = $dbu->getItemForItemtype($data["itemtype_link"])) {
                    if ($item instanceof Item_Devices) {
                        $field = $item->getDeviceTypeName(1);
                    } else {
                        $field = $item->getTypeName(1);
                    }
                }
                $change = sprintf(__('%1$s: %2$s'), $action_label, $data["old_value"]);
                break;
        }//fin du switch
    }
    echo $field . "<td>" . $change;
}

if (!empty($prev)) {
    echo "</td></tr>\n";
}
echo "</table><div class='alert alert-info center'>" . __('The list is limited to 100 items and 21 days', 'reports') . "</div></div>\n";

Html::footer();
