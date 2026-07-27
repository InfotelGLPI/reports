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

use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnInteger;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_equipmentbylocation", READ);

$report = new AutoReport(__('Number of equipments by location', 'reports'));

$dbu = new DbUtils();

$report->setColumns([new Column('entity', __('Entity')),
    new Column('location', __('Location')),
    new ColumnInteger('computernumber', _n('Computer', 'Computers', 2)),
    new ColumnInteger('networknumber', _n('Network', 'Networks', 2)),
    new ColumnInteger('monitornumber', _n('Monitor', 'Monitors', 2)),
    new ColumnInteger('printernumber', _n('Printer', 'Printers', 2)),
    new ColumnInteger('peripheralnumber', _n('Device', 'Devices', 2)),
    new ColumnInteger('phonenumber', _n('Phone', 'Phones', 2))]);

$criteria = [
    'SELECT' => [
        'glpi_entities.completename AS entity',
        'glpi_locations.completename AS location',
        'comp.computernumber',
        'net.networknumber',
        'mon.monitornumber',
        'pri.printernumber',
        'per.peripheralnumber',
        'pho.phonenumber'
    ],
    'FROM' => 'glpi_locations',
    'LEFT JOIN' => [
        'glpi_entities' => [
            'ON' => [
                'glpi_entities'  => 'id',
                'glpi_locations' => 'entities_id'
            ]
        ],
        // Computers
        'comp' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS computernumber, locations_id
                FROM glpi_computers
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_computers') . '
                GROUP BY locations_id
            ) AS comp'),
                    'ON' => [
                        'comp' => 'locations_id',
                        'glpi_locations' => 'id'
                    ]
        ],
        // Network
        'net' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS networknumber, locations_id
                FROM glpi_networkequipments
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_networkequipments') . '
                GROUP BY locations_id
            ) AS net'),
            'ON' => [
                'net' => 'locations_id',
                'glpi_locations' => 'id'
            ]
        ],
        // Monitors
        'mon' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS monitornumber, locations_id
                FROM glpi_monitors
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_monitors') . '
                GROUP BY locations_id
            ) AS mon'),
            'ON' => [
                'mon' => 'locations_id',
                'glpi_locations' => 'id'
            ]
        ],
        // Printers
        'pri' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS printernumber, locations_id
                FROM glpi_printers
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_printers') . '
                GROUP BY locations_id
            ) AS pri'),
            'ON' => [
                'pri' => 'locations_id',
                'glpi_locations' => 'id'
            ]
        ],
        // Peripherals
        'per' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS peripheralnumber, locations_id
                FROM glpi_peripherals
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_peripherals') . '
                GROUP BY locations_id
            ) AS per'),
            'ON' => [
                'per' => 'locations_id',
                'glpi_locations' => 'id'
            ]
        ],
        // Phones
        'pho' => [
            'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS phonenumber, locations_id
                FROM glpi_phones
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_phones') . '
                GROUP BY locations_id
            ) AS pho'),
            'ON' => [
                'pho' => 'locations_id',
                'glpi_locations' => 'id'
            ]
        ],
    ],
    'WHERE' => [],
    'GROUPBY'   => ['glpi_locations.id'],
    'ORDERBY' => [
        'entity',
        'location'
    ]
];

$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_locations'
    );

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
