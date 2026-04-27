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
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel, Benoit Machiavello
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
use GlpiPlugin\Reports\ColumnDateTime;
use GlpiPlugin\Reports\ColumnLink;

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

$report = new AutoReport(__('List of groups and members', 'reports'));

$report->setColumns([new Column('completename', __('Entity'),
    ['sorton' => 'completename']),
    new ColumnLink('groupid', __('Group'), 'Group',
        ['sorton' => 'groupid']),
    new ColumnLink('userid', __('Login'), 'User',
        ['sorton' => 'userid']),
    new Column('firstname', __('First name')),
    new Column('realname', __('Surname')),
    new ColumnDateTime('last_login', __('Last login'))]);

$criteria = [
    'SELECT' => ['glpi_entities.completename',
        'glpi_groups.id AS groupid',
        'glpi_users.id AS userid',
        'glpi_users.firstname AS firstname',
        'glpi_users.realname AS realname',
        'glpi_users.last_login',],
    'FROM' => 'glpi_groups',
    'LEFT JOIN'       => [
        'glpi_groups_users' => [
            'ON' => [
                'glpi_groups_users' => 'groups_id',
                'glpi_groups'          => 'id',
            ],
        ],
        'glpi_users' => [
            'ON' => [
                'glpi_groups_users' => 'users_id',
                'glpi_users'          => 'id',
            ],
        ],
        'glpi_entities' => [
            'ON' => [
                'glpi_groups' => 'entities_id',
                'glpi_entities'          => 'id',
            ],
        ],
    ],
    'WHERE' => [
        'glpi_users.is_deleted' => 0,
    ],
    'GROUPBY'   => ['completename', 'groupid', 'userid'],
];
$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_groups'
);

$criteria = $criteria + $report->getNewOrderBy('completename, groupid, userid');

$report->setSqlRequest($criteria);
$report->execute();

$report->footer();
