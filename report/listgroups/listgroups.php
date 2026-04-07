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

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

$report = new PluginReportsAutoReport(__('List of groups and members', 'reports'));

$report->setColumns([new PluginReportsColumn('completename', __('Entity')),
    new PluginReportsColumnLink('groupid', __('Group'), 'Group'),
    new PluginReportsColumnLink('userid', __('Login'), 'User'),
    new PluginReportsColumn('firstname', __('First name')),
    new PluginReportsColumn('realname', __('Surname')),
    new PluginReportsColumnDateTime('last_login', __('Last login'))]);

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
    'GROUPBY'   => ['completename', 'groupid'],
    'ORDERBY'   => ['completename', 'glpi_groups.name', 'glpi_users.name'],
];
$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
    'glpi_groups'
);

$report->setSqlRequest($criteria);
$report->execute();
