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

use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\StatusCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

$dbu = new DbUtils();

//TRANS: The name of the report = Not installed important software (plural)
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_softversioninstallations", READ);

$report   = new AutoReport(__('Software version installations', 'reports'));

$statever = new StatusCriteria(
    $report,
    'statever',
    __('Software version status', 'reports'),
);
$statever->setSqlField('glpi_softwareversions.states_id');

$statecpt = new StatusCriteria(
    $report,
    'statecpt',
    __('Computer status', 'reports'),
);
$statecpt->setSqlField('glpi_computers.states_id');

$report->displayCriteriasForm();

// Form validate and only one software with license
if ($report->criteriasValidated()) {

    $report->setSubNameAuto();

    $report->setColumns([new ColumnLink(
        'software',
        _n('Software', 'Software', 1),
        'Software',
        ['sorton' => 'software,version'],
    ),
        new ColumnLink('version', __('Version'), 'SoftwareVersion'),
        new Column('statever', __('Status')),
        new ColumnLink(
            'computer',
            __('Computer'),
            'Computer',
            ['sorton' => 'glpi_computers.name'],
        ),
        new Column('statecpt', __('Status')),
        new Column(
            'location',
            __('Location'),
            ['sorton' => 'location'],
        )]);

    $criteria = [
        'SELECT' => ['glpi_softwareversions.softwares_id AS software',
            'glpi_softwareversions.id AS version',
            'glpi_computers.id AS computer',
            'state_ver.name AS state_ver',
            'state_cpt.name AS state_cpt',
            'glpi_locations.completename AS location',
        ],
        'FROM' => 'glpi_softwareversions',
        'INNER JOIN'       => [
            'glpi_items_softwareversions' => [
                'ON' => [
                    'glpi_softwareversions'   => 'id',
                    'glpi_items_softwareversions'                  => 'softwareversions_id',
                ],
            ],
            'glpi_computers' => [
                'ON' => [
                    'glpi_computers'   => 'id',
                    'glpi_items_softwareversions'                  => 'items_id', [
                        'AND' => [
                            'glpi_items_softwareversions.itemtype' => 'Computer',
                        ],
                    ],
                ],
            ],
        ],
        'LEFT JOIN'       => [
            'glpi_locations' => [
                'ON' => [
                    'glpi_computers' => 'locations_id',
                    'glpi_locations'          => 'id',
                ],
            ],
            'glpi_states AS state_ver' => [
                'ON' => [
                    'state_ver' => 'id',
                    'glpi_softwareversions'          => 'states_id',
                ],
            ],
            'glpi_states AS state_cpt' => [
                'ON' => [
                    'state_cpt' => 'id',
                    'glpi_softwareversions'          => 'states_id',
                ],
            ],
        ],
        'WHERE' => [],
        'GROUPBY'   => ['software'],
    ];
    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_softwareversions',
    );

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $criteria = $criteria + $report->getNewOrderBy('software');

    $report->setSqlRequest($criteria);
    $report->execute();
}

$report->footer();
