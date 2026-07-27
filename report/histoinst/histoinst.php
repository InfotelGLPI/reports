<?php

/*
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
 @authors   Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

use Glpi\DBAL\QueryFunction;

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 1; // Really a big SQL request

global $DB;

Session::checkRight("plugin_reports_histoinst", READ);

$computer = new Computer();
$computer->checkGlobal(READ);

$software = new Software();
$software->checkGlobal(READ);

Html::header(__("History of last software's installations", 'reports'), $_SERVER['PHP_SELF'], "utils", "report");

Report::title();

echo "<div class='center'>";
echo "<table class='tab_cadre_fixe' cellpadding='5'>\n";
echo "<tr class='tab_bg_1 center'>".
      "<th colspan='4'>" . __("History of last software's installations", "reports") .
      "</th></tr>\n";

echo "<tr class='tab_bg_2'><th>". __('Update date') . "</th>" .
      "<th>". __('User') . "</th>".
      "<th>". __("Computer's name") . "</th>".
      "<th>". sprintf(__('%1$s (%2$s)'), _n('Software', 'Software', 1), __('version'))."</th></tr>\n";

//$sql = "SELECT  `glpi_logs`.`date_mod` AS dat, `linked_action`, `itemtype`, `itemtype_link`,
//               `old_value`, `new_value`, `glpi_computers`.`id` AS cid, `name`, `user_name`,
//               `items_id`, `entities_id`
//        FROM `glpi_logs`
//        LEFT JOIN `glpi_computers` ON (`glpi_logs`.`items_id` = `glpi_computers`.`id`)
//        WHERE `glpi_logs`.`date_mod` > DATE_SUB(Now(), INTERVAL 21 DAY)
//              AND `itemtype` = 'Computer'
//              AND `linked_action` = '" .Log::HISTORY_INSTALL_SOFTWARE ."'
//              AND `entities_id` = '" . $_SESSION["glpiactive_entity"] ."'
//        ORDER BY `glpi_logs`.`id` DESC
//        LIMIT 0,200";

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
        'linked_action' => [Log::HISTORY_INSTALL_SOFTWARE],
        'itemtype' => 'Computer',
        'glpi_logs.date_mod' => ['>', QueryFunction::dateSub(
            date: QueryFunction::now(),
            interval: '21',
            interval_unit: 'DAY'
        )],
    ],
    'ORDERBY' => 'glpi_logs.id DESC',
    'LIMIT' => '0,200',
];

$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_computers'
    );

$iterator = $DB->request($criteria);

$prev = "";
$class = "tab_bg_2";
foreach ($iterator as $data) {
   if (empty($data["name"])) {
      $data["name"] = "(".$data["cid"].")";
   }
   if ($prev == $data["dat"].$data["name"]) {
      echo "<br />";
   } else {
      if (!empty($prev)) {
         echo "</td></tr>\n";
      }
      $prev = $data["dat"].$data["name"];
      echo "<tr class='" . $class . " top'>".
            "<td class='center'>". Html::convDateTime($data["dat"]) . "</td>" .
            "<td>". htmlescape($data["user_name"]) . "&nbsp;</td>".
            "<td><a href='". Toolbox::getItemTypeFormURL('Computer') . "?id=" . (int) $data["cid"]."'>" .
                  htmlescape($data["name"]) . "</a></td>".
            "<td>";
      $class = ($class=="tab_bg_2" ? "tab_bg_1" : "tab_bg_2");
   }
   echo htmlescape($data["new_value"]);
}

if (!empty($prev)) {
   echo "</td></tr>\n";
}
echo "</table><div class='alert alert-info center'>". __('The list is limited to 200 items and 21 days', 'reports')."</div></div>\n";

Html::footer();
