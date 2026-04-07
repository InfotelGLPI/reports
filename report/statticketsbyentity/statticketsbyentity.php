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
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/

use Glpi\DBAL\QuerySubQuery;

//	Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1;

// Initialization of the variables
global $DB;

$dbu = new DbUtils();

$report = new PluginReportsAutoReport(__('Helpdesk requesters and tickets by entity', 'reports'));

//Report's search criterias
$prof = new PluginReportsDropdownCriteria(
    $report,
    'profiles_id',
    'glpi_profiles',
    __('Profile')
);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();

    //Names of the columns to be displayed
    $cols = [new PluginReportsColumn(
        'name',
        __('Entity'),
        ['sorton' => '`glpi_entities`.`completename`']
    ),
        new PluginReportsColumnInteger(
            'nbusers',
            __('Users count', 'reports'),
            ['withtotal' => true,
                'sorton'    => 'nbusers']
        ),
        new PluginReportsColumnInteger(
            'number',
            __('Tickets count', 'reports'),
            ['withtotal' => true,
                'sorton'    => 'number']
        ),
        new PluginReportsColumnDateTime(
            'mindate',
            __('Older', 'reports'),
            ['sorton' => 'mindate']
        ),
        new PluginReportsColumnDateTime(
            'maxdate',
            __('Newer', 'reports'),
            ['sorton' => 'maxdate']
        )];
    $report->setColumns($cols);

    $criteria_init = [
        'SELECT'    => [
            'COUNT' => '*',
        ],
        'FROM' => 'glpi_profiles_users',
        'INNER JOIN'       => [
            'glpi_entities' => [
                'ON' => [
                    'glpi_profiles_users'   => 'entities_id',
                    'glpi_entities'          => 'id',
                ],
            ],
        ],
        'WHERE' => [],
    ];

    $criteria_init['WHERE'] = $criteria_init['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $criteria_init['WHERE'] = $criteria_init['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_profiles_users'
    );

    $criteria = [
        'SELECT' => ['glpi_entities.completename AS name',
            new QuerySubQuery(
                $criteria_init,
                'nbusers'
            ),
            'COUNT' => 'glpi_tickets.id AS number',
            'MIN' => 'glpi_tickets.date AS mindate',
            'MAX' => 'glpi_tickets.date AS maxdate',
        ],
        'FROM' => 'glpi_entities',
        'LEFT JOIN'       => [
            'glpi_tickets' => [
                'ON' => [
                    'glpi_tickets'   => 'entities_id',
                    'glpi_entities'          => 'id',
                ],
            ],
        ],
        'WHERE' => [
            'glpi_tickets.is_deleted' => 0,
        ],
        'GROUPBY'   => ['glpi_entities.id'],
        'ORDERBY'   => [],
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_entities'
    );


    $criteria['ORDERBY'] = $criteria['ORDERBY'] + $report->getNewOrderBy('name');

    $report->setSqlRequest($criteria);

    $report->execute(['withtotal' => true]);


} else {
    Html::footer();
}
