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
use Glpi\DBAL\QueryFunction;
use GlpiPlugin\Reports\AutoReport;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;
$dbu = new DbUtils();

//TRANS: The name of the report = Licenses by expiration date
$report = new AutoReport(__('Licenses by expiration date', 'reports'));

$report->setColumns(['expire'       => __('Valid to', 'reports'),
    'name'         => __('License name'),
    'software'     => sprintf(
        __('%1$s - %2$s'),
        _n('Software', 'Software', 1),
        __('Purchase version')
    ),
    'serial'       => __('Serial number'),
    'completename' => __('Entity'),
    'comments'     => __('Comments'),
    'ordinateur'   => __('Computer')]);

$criteria = [
    'SELECT' => [
        'glpi_softwarelicenses.expire',
        'glpi_softwarelicenses.name',
        QueryFunction::concat(["glpi_softwares.name", new QueryExpression($DB::quoteValue(' ')), "buyversion.name"], 'software'),
        'glpi_softwarelicenses.serial',
        'glpi_entities.completename',
        'glpi_softwarelicenses.comment',
        'glpi_computers.name AS ordinateur',
    ],
    'FROM' => 'glpi_softwarelicenses',
    'LEFT JOIN'       => [
        'glpi_softwares' => [
            'ON' => [
                'glpi_softwarelicenses' => 'softwares_id',
                'glpi_softwares'          => 'id',
            ],
        ],
        'glpi_softwarelicensetypes' => [
            'ON' => [
                'glpi_softwarelicenses' => 'softwarelicensetypes_id',
                'glpi_softwarelicensetypes'          => 'id',
            ],
        ],
        'glpi_softwareversions AS buyversion' => [
            'ON' => [
                'glpi_softwarelicenses' => 'softwareversions_id_buy',
                'buyversion'          => 'id',
            ],
        ],
        'glpi_items_softwarelicenses' => [
            'ON' => [
                'glpi_items_softwarelicenses' => 'softwarelicenses_id',
                'glpi_softwarelicenses'          => 'id',
            ],
        ],
        'glpi_computers' => [
            'ON' => [
                'glpi_computers'   => 'id',
                'glpi_items_softwarelicenses'                  => 'items_id', [
                    'AND' => [
                        'glpi_items_softwarelicenses.itemtype' => 'Computer',
                    ],
                ],
            ],
        ],
        'glpi_entities' => [
            'ON' => [
                'glpi_softwares' => 'entities_id',
                'glpi_entities'          => 'id',
            ],
        ],

    ],
    'WHERE' => ['glpi_softwares.is_deleted = 0',
        'glpi_softwares.is_template = 0',
        'NOT'       => ['glpi_softwarelicenses.expire' => null]],
    'GROUPBY'   => ['glpi_softwarelicenses.expire',
        'glpi_softwarelicenses.name'],
    'ORDERBY'   => 'glpi_softwarelicenses.expire, glpi_softwarelicenses.name',
];
$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_softwarelicenses'
);

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
