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

use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDate;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\ColumnType;
use GlpiPlugin\Reports\ColumnTypeLink;
use GlpiPlugin\Reports\DateIntervalCriteria;
use GlpiPlugin\Reports\DropdownCriteria;
use GlpiPlugin\Reports\TextCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

$dbu = new DbUtils();

//TRANS: The name of the report = Search in the financial information (plural)
$report = new AutoReport(__('Search in the financial information', 'reports'));

//Report's search criterias
new DateIntervalCriteria($report, 'order_date', __('Order date'));
new DateIntervalCriteria($report, 'buy_date', __('Date of purchase'));
new DateIntervalCriteria($report, 'delivery_date', __('Delivery date'));
new DateIntervalCriteria($report, 'use_date', __('Startup date'));
new DateIntervalCriteria($report, 'inventory_date', __('Date of last physical inventory'));
new TextCriteria($report, 'immo_number', __('Immobilization number'));
new TextCriteria($report, 'order_number', __('Order number'));
new TextCriteria($report, 'delivery_number', __('Delivery form'));
new DropdownCriteria($report, 'budgets_id', 'glpi_budgets', __('Budget'));

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {

   // Report title
   $report->setSubNameAuto();

   // Report Columns
   $cols = [new ColumnType('itemtype', __('Type')),
            new ColumnTypeLink('items_id', __('Item'), 'itemtype',
                                            ['with_comment' => 1]),
            new ColumnDate('order_date', __('Order date')),
            new Column('order_number', __('Order number')),
            new ColumnDate('buy_date', __('Date of purchase')),
            new Column('delivery_date', __('Delivery date')),
            new Column('delivery_number', __('Delivery form')),
            new Column('immo_number', __('Immobilization number')),
            new ColumnDate('use_date', __('Startup date')),
            new ColumnDate('inventory_date', __('Date of last physical inventory')),
            new ColumnLink('budgets_id', __('Budget'), 'Budget')];

   $report->setColumns($cols);

   $itemtypes = ['Cartridge', 'CartridgeItem', 'Consumable', 'ConsumableItem',
       'Software', 'Line', 'Certificate', 'Appliance', 'Domain',
       'Item_DeviceSimcard', 'SoftwareLicense'];
   // Build SQL request
//   $sql = "SELECT *
//           FROM `glpi_infocoms`
//           WHERE `itemtype` NOT IN ()".
//           $report->addSqlCriteriasRestriction().
//           $dbu->getEntitiesRestrictRequest('AND', 'glpi_infocoms').
//          "ORDER BY `itemtype`";

//   $report->execute();

    $criteria = [
        'SELECT' => ['*'],
        'FROM' => 'glpi_infocoms',
        'WHERE' =>
            ['itemtype' => ['NOT IN', $itemtypes]],
        'ORDERBY'   => ['itemtype'],
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_infocoms'
        );

    $report->setGroupBy(['itemtype']);

    $report->setSqlRequest($criteria);

    $report->execute();

}

$report->footer();
