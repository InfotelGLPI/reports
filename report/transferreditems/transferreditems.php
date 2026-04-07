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
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel, Stéphane Savona
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */


$USEDBREPLICATE       = 1;
$DBCONNECION_REQUIRED = 0;

$dbu = new DbUtils();

//TRANS: The name of the report = List of transfered objects
$report = new PluginReportsAutoReport(__('List of transfered objects', 'reports'));

// Search criterias
new PluginReportsDateIntervalCriteria($report, "`glpi_logs`.`date_mod`");

$types = [];
foreach (['CartridgeItem', 'Computer', 'Enclosure', 'Monitor', 'NetworkEquipment',
    'PassiveDCEquipment', 'PDU', 'Peripheral', 'Phone', 'Printer', 'Rack', 'Software',
    'SoftwareLicense'] as $type) {
    $label        = call_user_func([$type, 'getTypeName']);
    $types[$type] = $label;
}

ksort($types);
$typecritera = new PluginReportsItemTypeCriteria($report, "itemtype", __('Type'), $types);

$report->displayCriteriasForm();

// Declare columns
if ($report->criteriasValidated()) {
    $itemtype = $_POST['itemtype'];
    $table = $dbu->getTableForItemType($itemtype);

    $columns = [new PluginReportsColumnLink(
        'items_id',
        __('Name'),
        $itemtype,
        ['with_comment' => 1]
    ),
        new PluginReportsColumn('otherserial', __('Inventory number')),
        new PluginReportsColumn('old_value', __('Source entity', 'reports')),
        new PluginReportsColumn('new_value', __('Target entity', 'reports')),
        new PluginReportsColumnDateTime('date_mod', __('Transfert date', 'reports'))];
    $report->setColumns($columns);

    $otherserial = '';
    if (($itemtype != 'CartridgeItem')
          && ($itemtype != 'ConsumableItem')) {
        $otherserial = "$table.otherserial";
    }

    $criteria = [
        'SELECT' => [$table.'.id AS items_id',
            $table.'.name',
            $otherserial,
            'glpi_logs.date_mod AS date_mod',
            'glpi_logs.itemtype AS itemtype',
            'glpi_logs.itemtype_link',
            'glpi_logs.old_value',
            'glpi_logs.new_value',
        ],
        'FROM' => 'glpi_logs',
        'LEFT JOIN'       => [
            $table => [
                'ON' => [
                    $table       => 'id',
                    'glpi_logs'  => 'items_id', [
                        'AND' => [
                            'glpi_logs.itemtype' => $itemtype,
                        ],
                    ],
                ]
            ],
        ],
        'WHERE' => [
            'id_search_option' => 80,
        ],
        'ORDERBY' => 'date_mod ASC',
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $report->setSqlRequest($criteria);

    $report->execute();

} else {
    Html::footer();
}
