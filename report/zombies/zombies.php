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

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use GlpiPlugin\Reports\ArrayCriteria;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDate;
use GlpiPlugin\Reports\ColumnInteger;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\TextCriteria;

$USEDBREPLICATE         = 0;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

//TRANS: The name of the report = Users with no right
$report = new AutoReport(__('Users with no right', 'reports'));

$name = new TextCriteria($report, 'name', __('Login'));

$tab = [0 => __('No'),
    1 => __('Yes')];
$filter = new ArrayCriteria($report, 'tickets', __('With no ticket', 'reports'), $tab);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();
    $report->delCriteria('tickets');

    $cols = [
//        new ColumnItemCheckbox('id', 'User'),
        new ColumnLink('id2', __('User'), 'User', ['with_comment' => true,
            'with_navigate' => true]),
        new Column('name', __('Login'), ['sorton' => 'name']),
        new Column('email', __('Email')),
        new Column('phone', __('Phone')),
        new Column('location', __('Location')),
        new ColumnDate('last_login', __('Last login'), ['sorton' => 'last_login'])];

    if (!$filter->getParameterValue()) {
        $cols[] = new ColumnInteger('nb1', __('Writer'), ['with_zero' => false,
            'sorton'    => 'nb1']);
        $cols[] = new ColumnInteger('nb2', __('Requester'), ['with_zero' => false,
            'sorton'    => 'nb2']);
        $cols[] = new ColumnInteger('nb3', __('Observer'), ['with_zero' => false,
            'sorton'    => 'nb3']);
        $cols[] = new ColumnInteger('nb4', __('Technician'), ['with_zero' => false,
            'sorton'    => 'nb4']);
    }

    $report->setColumns($cols);

    $criteria = [
        'SELECT' => [
            'glpi_users.id',
            'glpi_users.id AS id2',
            'glpi_users.name',
            'glpi_useremails.email',
            'glpi_users.phone',
            'glpi_locations.completename AS location',
            'glpi_users.last_login',
            new QueryExpression(
                '(SELECT COUNT(*)
              FROM glpi_tickets
              WHERE glpi_users.id = glpi_tickets.users_id_recipient) AS nb1'
            ),
            new QueryExpression(
                '(SELECT COUNT(*)
              FROM glpi_tickets_users
              WHERE glpi_users.id = glpi_tickets_users.users_id
              AND glpi_tickets_users.type = ' . CommonITILActor::REQUESTER . ') AS nb2'
            ),
            new QueryExpression(
                '(SELECT COUNT(*)
              FROM glpi_tickets_users
              WHERE glpi_users.id = glpi_tickets_users.users_id
              AND glpi_tickets_users.type = ' . CommonITILActor::OBSERVER . ') AS nb3'
            ),
            new QueryExpression(
                '(SELECT COUNT(*)
              FROM glpi_tickets_users
              WHERE glpi_users.id = glpi_tickets_users.users_id
              AND glpi_tickets_users.type = ' . CommonITILActor::ASSIGN . ') AS nb4'
            ),
        ],
        'FROM' => 'glpi_users',
        'LEFT JOIN' => [
            'glpi_locations' => [
                'ON' => [
                    'glpi_locations' => 'id',
                    'glpi_users'     => 'locations_id'
                ]
            ],
            'glpi_useremails' => [
                'ON' => [
                    'glpi_useremails' => 'users_id',
                    'glpi_users'      => 'id',
                    [
                        'AND' => [
                            'glpi_useremails.is_default' => 1
                        ]
                    ]
                ]
            ]
        ],
        'WHERE' => [
            'glpi_users.is_deleted' => 0,
            'NOT' => [
                'glpi_users.id' => new QuerySubQuery([
                    'SELECT' => ['users_id'],
                    'FROM'   => 'glpi_profiles_users'
                ])
            ]
        ],
        'HAVING' => [],
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    if ($filter->getParameterValue()) {
        $criteria['HAVING'] = $criteria['HAVING'] +  [new QueryExpression("nb1=0 AND nb2=0 AND nb3=0 AND nb4=0")];
    }

    $criteria = $criteria + $report->getNewOrderBy('name');

    $report->setSqlRequest($criteria);
    $report->execute();//['withmassiveaction' => 'User']

}

$report->footer();
