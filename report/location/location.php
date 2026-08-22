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

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0; // not really a big SQL request

global $DB;

// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_location", READ);

$report = new AutoReport(__('Location tree', 'reports'));

$report->setColumns([new Column(
    'entity',
    __('Entity'),
    ['sorton' => 'entity,location'],
),
    new Column('location', __('Location'), ['sorton' => 'location']),
    new ColumnLink(
        'link',
        _n('Link', 'Links', 2, 'reports'),
        'Location',
        ['sorton' => '`glpi_locations`.`name`'],
    )]);

// SQL statement
$criteria = [
    'SELECT' => ['glpi_entities.completename AS entity',
        'glpi_locations.completename AS location',
        'glpi_locations.id AS link',
    ],
    'FROM' => 'glpi_locations',
    'LEFT JOIN'       => [
        'glpi_entities' => [
            'ON' => [
                'glpi_locations' => 'entities_id',
                'glpi_entities'          => 'id',
            ],
        ],
    ],
    'WHERE' => [],
];
$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_locations',
);

$criteria = $criteria + $report->getNewOrderBy('entity,location');

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
