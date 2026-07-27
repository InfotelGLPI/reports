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

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1;

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QuerySubQuery;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\DateIntervalCriteria;

global $DB;

//titre du rapport dans la liste de selection,  soit en dur ici, soit mettre à jour la variable dans les fichiers de traduction;
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_statusertask", READ);

$report = new AutoReport(__('Tasks list per user', 'reports'));

//critère de selection;
$date = new DateIntervalCriteria($report, '`glpi_tickettasks`.`date`', __('Tasks created', 'reports'));

$report->displayCriteriasForm();

$display_type = Search::HTML_OUTPUT;

if ($report->criteriasValidated()) {

    $cols = [new Column('realname', __('User')),
            new Column('date', __('Date')),
            new Column('ticketid', __('Ticket task id')),
            new Column('duree', __('Duration')),
            new Column('nbretask', __('Number created tasks', 'reports')),
            new Column('total', __('Total duration'))
    ];

    $report->setColumns($cols);

//    $query = "SELECT DATE_FORMAT(`glpi_tickettasks`.`date`, '%d/%m/%Y') AS date,
//                    `glpi_users`.`realname`,
//                    `glpi_tickettasks`.`id` AS ticketid,
//                    SEC_TO_TIME( sum( glpi_tickettasks.actiontime ) )  AS duree,
//                    count(`glpi_tickettasks`.`tickets_id` ) AS nbretask,
//                    (
//                    SELECT SEC_TO_TIME(sum(glpi_tickettasks.actiontime ))
//                     FROM `glpi_tickettasks`
//                     INNER JOIN  `glpi_users` ON (`glpi_tickettasks`.`users_id` = `glpi_users`.`id`)
//                     WHERE `glpi_users`.`id` =".Session::getLoginUserID(false) ." ".
//                           $date->getSqlCriteriasRestriction()." ) as total
//
//              FROM `glpi_tickettasks`
//              INNER JOIN  `glpi_users` ON (`glpi_tickettasks`.`users_id` = `glpi_users`.`id`)
//              WHERE `glpi_users`.`id` = ".Session::getLoginUserID(false) ." ".
//                    $date->getSqlCriteriasRestriction()."
//              GROUP BY date, realname, ticketid";
//

    $criteria_init = [
        'SELECT'    => [
            new QueryExpression("SEC_TO_TIME(SUM(" . $DB->quoteName("glpi_tickettasks.actiontime") . "))"),
        ],
        'FROM' => 'glpi_tickettasks',
        'INNER JOIN'       => [
            'glpi_users' => [
                'ON' => [
                    'glpi_tickettasks'   => 'users_id',
                    'glpi_users'          => 'id',
                ],
            ],
        ],
        'WHERE' => [
            'glpi_users.id' => Session::getLoginUserID(false),
        ],
    ];

    $criteria_init['WHERE'] = $criteria_init['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $criteria = [
        'SELECT' => [
            new QueryExpression("DATE_FORMAT(" . $DB->quoteName("glpi_tickettasks.date") . ", '%d/%m/%Y') AS tdate"),
            'glpi_users.realname',
            'glpi_tickettasks.id AS ticketid',
            new QueryExpression("SEC_TO_TIME(SUM(" . $DB->quoteName("glpi_tickettasks.actiontime") . ")) AS duree"),
            'COUNT' => 'glpi_tickettasks.tickets_id AS nbretask',
            new QuerySubQuery(
                $criteria_init,
                'total'
            ),
        ],
        'FROM' => 'glpi_tickettasks',
        'INNER JOIN'       => [
            'glpi_users' => [
                'ON' => [
                    'glpi_tickettasks'   => 'users_id',
                    'glpi_users'          => 'id',
                ],
            ],
        ],
        'WHERE' => [
            'glpi_users.id' => Session::getLoginUserID(false),
        ],
        'GROUPBY' => ['tdate', 'realname', 'ticketid'],
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $report->setGroupBy('total');

    $report->setSqlRequest($criteria);

    $report->execute();

}

$report->footer();
