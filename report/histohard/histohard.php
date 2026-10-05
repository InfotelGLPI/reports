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
use Glpi\DBAL\QueryFunction;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1; // Really a big SQL request

global $DB;

Session::checkRight("plugin_reports_histohard", READ);

$computer = new Computer();
$dbu      = new DbUtils();
$computer->checkGlobal(READ);

Html::header(__("History of last hardware's installations", 'reports'), '', "utils", "report");

Report::title();

$table = [
    'class'       => 'tab_cadre_fixe',
    'header_rows' => [
        ['class' => 'tab_bg_1 center', 'cells' => [[
            'value'   => __("History of last hardware's installations", 'reports'),
            'colspan' => 5,
        ]]],
        ['cells' => [
            ['value' => __('Date of inventory', 'reports')],
            ['value' => __('User')],
            ['value' => __('Network device')],
            ['value' => __('Field')],
            ['value' => __('Modification', 'reports')],
        ]],
    ],
    'rows'        => [],
];

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
            interval_unit: 'DAY',
        )],
    ],
    'ORDERBY' => 'glpi_logs.id DESC',
    'LIMIT' => '0,100',
];

$criteria['WHERE'][] = getEntitiesRestrictCriteria(
    'glpi_computers',
);

$iterator = $DB->request($criteria);

$prev      = "";
$class     = "tab_bg_2";
$prevclass = $class;
foreach ($iterator as $data) {
    if (empty($data["name"])) {
        $data["name"] = "(" . $data["cid"] . ")";
    }
    if ($prev == $data["dat"] . $data["name"]) {
        // Same inventory of the same computer: only the field and the change are shown
        $row = [
            'class' => $prevclass . ' top',
            'cells' => [['value' => ''], ['value' => ''], ['value' => '']],
        ];
    } else {
        $prev = $data["dat"] . $data["name"];
        $row = [
            'class' => $class . ' top',
            'cells' => [
                ['value' => Html::convDateTime($data["dat"])],
                ['value' => $data["user_name"]],
                [
                    'value' => $data["name"],
                    'href'  => Toolbox::getItemTypeFormURL('Computer') . "?id=" . (int) $data["cid"],
                ],
            ],
        ];
        $prevclass = $class;
        $class = ($class == "tab_bg_2" ? "tab_bg_1" : "tab_bg_2");
    }
    $field  = "";
    // Initialize $change too: when linked_action is falsy the switch is skipped,
    // and without a reset the previous row's $change would be echoed again.
    $change = "";
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
                // glpi_logs values are stored raw and may contain attacker-controlled
                // HTML/JS (e.g. via an inventory-supplied device field): the template escapes
                // the whole cell (same as histoinst.php).
                $change = sprintf(__('%1$s: %2$s'), $action_label, $data["new_value"]);
                break;

            case Log::HISTORY_UPDATE_DEVICE:
                $field = NOT_AVAILABLE;
                $change = '';
                // glpi_logs.itemtype_link is stored raw and constrained by nothing: a row written
                // by an older version, by a third-party plugin or by an import may carry no '#'
                // separator at all, or name a class that is no longer installed. The two
                // neighbouring branches resolve their class through $dbu->getItemForItemtype(),
                // which validates it; this one went straight to $linktype::getDeviceType(), so a
                // single malformed row turned the whole report into an uncaught Error - a 500 for
                // every user, for as long as the row stayed inside the 21-day window. Item_Devices
                // is the only hierarchy declaring getDeviceType() and getSpecificities(), which
                // makes it the natural allow-list here, and is_a() with $allow_string autoloads
                // the class and rejects an unknown name in one go.
                $linktype_field = explode('#', (string) $data["itemtype_link"]);
                $linktype       = $linktype_field[0];
                $fieldval       = $linktype_field[1] ?? '';
                if (!is_a($linktype, Item_Devices::class, true)) {
                    break;
                }
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
                    sprintf(__('%1$s by %2$s'), $data["old_value"], $data["new_value"]),
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
    $row['cells'][] = ['value' => $field];
    $row['cells'][] = ['value' => $change];
    $table['rows'][] = $row;
}

TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', [
    'tables'          => [$table],
    'footer_messages' => [['type' => 'info', 'text' => __('The list is limited to 100 items and 21 days', 'reports')]],
]);

Html::footer();
