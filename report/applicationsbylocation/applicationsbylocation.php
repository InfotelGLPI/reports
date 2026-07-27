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
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\LocationCriteria;
use GlpiPlugin\Reports\SoftwareCategoriesCriteria;
use GlpiPlugin\Reports\SoftwareCriteria;
use GlpiPlugin\Reports\StatusCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

$dbu = new DbUtils();

// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_applicationsbylocation", READ);

$report = new AutoReport(__('Applications by locations and versions', 'reports'));

$softwarecategories = new SoftwareCategoriesCriteria(
    $report,
    'softwarecategories',
    __('Software category')
);
$softwarecategories->setSqlField('glpi_softwarecategories.id');

$software = new SoftwareCriteria($report, 'software', __('Application', 'reports'));
$software->setSqlField('glpi_softwares.id');

$statecpt = new StatusCriteria($report, 'statecpt', __('Computer status', 'reports'));
$statecpt->setSqlField('glpi_computers.states_id');

$location = new LocationCriteria($report, 'location', _n('Location', 'Locations', 1));
$location->setSqlField('glpi_computers.locations_id');


$report->displayCriteriasForm();

// Form validate and only one software with license
//if ($report->criteriasValidated()) {
$report->setSubNameAuto();

$report->setColumns([new ColumnLink(
    'soft',
    _n('Software', 'Software', 1),
    'Software',
    ['sorton' => 'soft,version']
),
    new ColumnLink(
        'locat',
        _n('Location', 'Locations', 1),
        'Location',
        ['sorton' => 'glpi_locations.name']
    ),
    new ColumnLink(
        'computer',
        _n('Computer', 'Computers', 1),
        'Computer',
        ['sorton' => 'glpi_computers.name']
    ),
    new Column('statecpt', _n('Status', 'Statuses', 1)),
    new ColumnLink(
        'version',
        __('Version'),
        'SoftwareVersion'
    ),
    new ColumnLink(
        'user',
        _n('User', 'Users', 1),
        'User',
        ['sorton' => 'glpi_users.name']
    )]);

// SQL statement
$criteria = [
    'SELECT' => ['glpi_softwareversions.softwares_id AS soft',
        'glpi_softwareversions.name AS software',
        'glpi_locations.id AS locat',
        'glpi_computers.id AS computer',
        'state_ver.name AS statever',
        'state_cpt.name AS statecpt',
        'glpi_locations.name AS location',
        'glpi_softwareversions.id AS version',
        'glpi_computers.users_id AS user',
    ],
    'FROM' => 'glpi_softwareversions',
    'INNER JOIN'       => [
        'glpi_items_softwareversions' => [
            'ON' => [
                'glpi_items_softwareversions' => 'softwareversions_id',
                'glpi_softwareversions'          => 'id',
            ],
        ],
        'glpi_computers' => [
            'ON' => [
                'glpi_items_softwareversions'   => 'items_id',
                'glpi_computers'                  => 'id', [
                    'AND' => [
                        'glpi_items_softwareversions.itemtype' => 'Computer',
                    ],
                ],
            ],
        ],
        'glpi_softwares' => [
            'ON' => [
                'glpi_softwareversions' => 'softwares_id',
                'glpi_softwares'          => 'id',
            ],
        ],
    ],
    'LEFT JOIN'       => [
        'glpi_softwarecategories' => [
            'ON' => [
                'glpi_softwares' => 'softwarecategories_id',
                'glpi_softwarecategories'          => 'id',
            ],
        ],
        'glpi_locations' => [
            'ON' => [
                'glpi_computers' => 'locations_id',
                'glpi_locations'          => 'id',
            ],
        ],
        'glpi_states AS state_ver' => [
            'ON' => [
                'glpi_softwareversions' => 'states_id',
                'state_ver'          => 'id',
            ],
        ],
        'glpi_states AS state_cpt' => [
            'ON' => [
                'glpi_computers' => 'states_id',
                'state_cpt'          => 'id',
            ],
        ],
    ],
    'WHERE' => [],
];
$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_softwareversions'
);

$criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

$criteria = $criteria + $report->getNewOrderBy('soft, locat');

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
