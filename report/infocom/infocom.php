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

/*
 * ----------------------------------------------------------------------
 *    Big UNION to have a report including all inventory
 * ----------------------------------------------------------------------
 */

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryUnion;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDate;
use GlpiPlugin\Reports\ColumnFloat;
use GlpiPlugin\Reports\ColumnInteger;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\ColumnType;
use GlpiPlugin\Reports\ColumnTypeLink;
use GlpiPlugin\Reports\DateIntervalCriteria;
use GlpiPlugin\Reports\DropdownCriteria;
use GlpiPlugin\Reports\ItemTypeCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB, $CFG_GLPI;

global $DB;
$dbu = new DbUtils();
/*
 * TODO : add more criteria
 *
 * - num_immo not empry
 * - otherserial not empty
 * - etc
 *
 */

$report = new AutoReport(__('Financial information', 'reports'));

$ignored = ['Cartridge', 'CartridgeItem', 'Consumable', 'ConsumableItem', 'Software', 'Line',
    'Certificate', 'Appliance', 'Domain', 'Item_DeviceSimcard', 'SoftwareLicense'];

$date = new DateIntervalCriteria(
    $report,
    '`glpi_infocoms`.`buy_date`',
    __('Date of purchase')
);
$type = new ItemTypeCriteria($report, 'itemtype', '', 'infocom_types', $ignored);
$budg = new DropdownCriteria(
    $report,
    '`glpi_infocoms`.`budgets_id`',
    'Budget',
    __('Budget')
);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();

    $cols = [new ColumnType('itemtype', __('Item type')),
        new Column('manufacturer', __('Manufacturer')),
        new Column('type', __('Type')),
        new Column('model', __('Model')),
        new ColumnTypeLink('itemid', __('Name'), 'itemtype'),
        new Column('serial', __('Serial number')),
        new Column('otherserial', __('Inventory number')),
        new Column('location', __('Location')),
        new Column('building', __('Building number')),
        new Column('room', __('Room number')),
        new ColumnLink('groups_id', __('Group'), 'Group'),
        new Column('state', __('Status')),
        new Column('immo_number', __('Immobilization number')),
        new ColumnDate('buy_date', __('Date of purchase')),
        new ColumnDate('use_date', __('Startup date')),
        new ColumnDate('warranty_date', __('Start date of warranty')),
        new ColumnInteger('warranty_duration', __('Warranty duration')),
        new ColumnInteger('warranty_info', __('Warranty information')),
        new ColumnLink('suppliers_id', __('Supplier'), "Supplier"),
        new ColumnDate('order_date', __('Order date')),
        new Column('order_number', __('Order number')),
        new ColumnDate('delivery_date', __('Delivery date')),
        new Column('delivery_number', __('Delivery form')),
        new ColumnFloat('value', __('Value')),
        new ColumnFloat('warranty_value', __('Warranty extension value')),
        new ColumnInteger('sink_time', __('Amortization duration')),
        new ColumnInteger('sink_type', __('Amortization type')),
        new ColumnFloat('sink_coeff', __('Amortization coefficient')),
        new Column('bill', __('Invoice number')),
        new Column('budget', __('Budget')),
        new ColumnDate('inventory_date', __('Date of last physical inventory'))];

    $report->setColumns($cols);
    $sel = $type->getParameterValue();
    if ($sel && $sel != "all") {
        $types = [$sel];
    } else {
        $types = array_diff($CFG_GLPI['infocom_types'], $ignored);
    }

    $queries = [];

    foreach ($types as $itemtype) {
        $item = new $itemtype();
        $table = $item->getTable();

        $criteria['SELECT'] = [
            new QueryExpression("'$itemtype' AS listitemtype"),
            $table . '.id AS itemid',
        ];

        $criteria['FROM'] = [$table];
        $criteria['LEFT JOIN'] = [];


        //        if ($itemtype == 'SoftwareLicense') {
        //            $criteria['SELECT'] = array_merge($criteria['SELECT'],['glpi_manufacturers.name AS manufacturer']);
        //
        //            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
        //                    'glpi_softwares' => [
        //                        'ON' => [
        //                            'glpi_softwarelicenses' => 'softwares_id',
        //                            'glpi_softwares'          => 'id',
        //                        ],
        //                    ],
        //                    'glpi_manufacturers' => [
        //                        'ON' => [
        //                            'glpi_softwares' => 'manufacturers_id',
        //                            'glpi_manufacturers'          => 'id',
        //                        ],
        //                    ],
        //                ];
        //
        //        }

        if ($item->isField('manufacturers_id')) {

            $criteria['SELECT'] = array_merge($criteria['SELECT'], ['glpi_manufacturers.name AS manufacturer']);

            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                'glpi_manufacturers' => [
                    'ON' => [
                        $table => 'manufacturers_id',
                        'glpi_manufacturers'          => 'id',
                    ],
                ],
            ];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS manufacturer")]);
        }

        $typeclass = $itemtype . 'Type';
        $typetable = $dbu->getTableForItemType($typeclass);

        if ($DB->tableExists($typetable)) {
            $typeitem  = new $typeclass();
            $typefkey  = $typeitem->getForeignKeyField();

            $criteria['SELECT'] = array_merge($criteria['SELECT'], [$typetable . '.name AS type']);
            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                $typetable => [
                    'ON' => [
                        $table => $typefkey,
                        $typetable          => 'id',
                    ],
                ],
            ];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS type")]);
        }

        $modelclass = $itemtype . 'Model';
        $modeltable = $dbu->getTableForItemType($modelclass);
        //        if ($itemtype == 'SoftwareLicense') {
        //
        //            $criteria['SELECT'] = array_merge($criteria['SELECT'],[ QueryFunction::concat(["glpi_softwares.name", new QueryExpression($DB::quoteValue(' ')), "buyversion.name"], 'model'),]);
        //
        //            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
        //                    'glpi_softwareversions AS buyversion' => [
        //                        'ON' => [
        //                            'glpi_softwarelicenses' => 'softwareversions_id_buy',
        //                            'buyversion'          => 'id',
        //                        ],
        //                    ],
        //                ];
        //
        //        }
        if ($DB->tableExists($modeltable)) {
            $modelitem  = new $modelclass();
            $modelitem  = $modelitem->getForeignKeyField();

            $criteria['SELECT'] = array_merge($criteria['SELECT'], [$modeltable . '.name AS model']);

            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                $modeltable => [
                    'ON' => [
                        $table => $modelitem,
                        $modeltable          => 'id',
                    ],
                ],
            ];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS model")]);
        }

        if ($item->isField('serial')) {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [$table . '.serial']);
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS serial")]);
        }

        $criteria['WHERE'] = [];
        if ($item->isField('otherserial')) {

            $criteria['SELECT'] = array_merge($criteria['SELECT'], [$table . '.otherserial']);

            $criteria['WHERE'] = $criteria['WHERE'] + ['NOT' => [$table . '.otherserial' => null,
                'glpi_infocoms.immo_number' => null]];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS otherserial")]);
        }

        $criteria['SELECT'] = array_merge($criteria['SELECT'], ['glpi_groups_items.groups_id']);

        $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + ['glpi_groups_items' => [
                'ON' => [
                    $table   => 'id',
                    'glpi_groups_items'                  => 'items_id', [
                        'AND' => [
                            'glpi_groups_items.itemtype' => $itemtype,
                        ],
                    ],
                ]
            ]
        ];

        if ($item->isField('states_id')) {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], ['glpi_states.name AS state']);
            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                'glpi_states' => [
                    'ON' => [
                        'glpi_states'   => 'id',
                        $table                => 'states_id',
                    ],
                ],
            ];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS state")]);
        }

        if ($item->isField('locations_id')) {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], ['glpi_locations.completename AS location',
                'glpi_locations.building',
                'glpi_locations.room']);

            $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                'glpi_locations' => [
                    'ON' => [
                        'glpi_locations'   => 'id',
                        $table             => 'locations_id',
                    ],
                ],
            ];
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'], [new QueryExpression("'' AS location"),
                new QueryExpression("'' AS building"),
                new QueryExpression("'' AS room")]);
        }

        $criteria['SELECT'] = array_merge($criteria['SELECT'], ['glpi_infocoms.*',
            'glpi_infocoms.suppliers_id AS supplier',
            'glpi_budgets.name AS budget']);

        $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
            'glpi_infocoms' => [
                'ON' => [
                    $table          => 'id',
                    'glpi_infocoms' => 'items_id', [
                        'AND' => [
                            'glpi_infocoms.itemtype' => $itemtype,
                        ],
                    ],
                ],
            ],
            'glpi_budgets' => [
                'ON' => [
                    'glpi_budgets'   => 'id',
                    'glpi_infocoms'  => 'budgets_id',
                ],
            ],
        ];

        if ($item->maybeDeleted()) {
            $criteria['WHERE'] = $criteria['WHERE'] + [$table . '.is_deleted' => 0];
        }

        if ($item->maybeTemplate()) {
            $criteria['WHERE'] = $criteria['WHERE'] + [$table . '.is_template' => 0];
        }

        if ($item->isEntityAssign()) {
            $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                $table
            );
        }

        $criteria['WHERE'] = $criteria['WHERE'] + $budg->getNewSqlCriteriasRestriction();

        $criteria['WHERE'] = $criteria['WHERE'] + $date->getNewSqlCriteriasRestriction();

        $queries[] = $criteria;
    }


    $union = new QueryUnion($queries, true);

    $req = ['FROM' => $union];
    $report->setSqlRequest($req);

    $report->execute();

}

$report->footer();
