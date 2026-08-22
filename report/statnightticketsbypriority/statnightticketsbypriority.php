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

use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDateTime;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\ColumnMap;
use GlpiPlugin\Reports\DateIntervalCriteria;
use GlpiPlugin\Reports\TimeIntervalCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB, $CFG_GLPI;

$dbu = new DbUtils();

//TRANS: The name of the report = Tickets opened at night, sorted by priority
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_statnightticketsbypriority", READ);

$report = new AutoReport(__('Tickets opened at night, sorted by priority', 'reports'));

//Report's search criterias
new DateIntervalCriteria($report, '`glpi_tickets`.`date`', __('Opening date'));

$timeInterval = new TimeIntervalCriteria($report, '`glpi_tickets`.`date`');

//Criterias default values
$timeInterval->setStartTime($CFG_GLPI['planning_end']);
$timeInterval->setEndtime($CFG_GLPI['planning_begin']);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();

    //Names of the columns to be displayed
    $report->setColumns([new ColumnMap(
        'priority',
        __('Priority'),
        [],
        ['sorton' => '`priority`, `date`'],
    ),
        new ColumnDateTime(
            'date',
            __('Opening date'),
            ['sorton' => '`date`'],
        ),
        new Column('id2', __('ID')),
        new ColumnLink('id', __('Title'), 'Ticket'),
        new Column(
            'groupname',
            __('Group'),
            ['sorton' => '`glpi_groups_tickets`.`groups_id`, `date`'],
        )]);

    //   $query = "SELECT `glpi_tickets`.`priority`, `glpi_tickets`.`date` , `glpi_tickets`.`id`,
    //                    `glpi_tickets`.`id` AS id2, `glpi_groups`.`name` as groupname
    //             FROM `glpi_tickets`
    //             LEFT JOIN `glpi_groups_tickets`
    //                  ON (`glpi_groups_tickets`.`tickets_id` = `glpi_tickets`.`id`
    //                      AND `glpi_groups_tickets`.`type` = '".CommonITILActor::ASSIGN."')
    //             LEFT JOIN `glpi_groups` ON (`glpi_groups_tickets`.`groups_id` = `glpi_groups`.`id`)
    //             WHERE `glpi_tickets`.`status` NOT IN ('".implode("', '",
    //                                                              array_merge(Ticket::getSolvedStatusArray(),
    //                                                                          Ticket::getClosedStatusArray()))."')
    //                  AND NOT `glpi_tickets`.`is_deleted` ".
    //                  $report->addSqlCriteriasRestriction() .
    //                  $dbu->getEntitiesRestrictRequest(' AND ', 'glpi_tickets').
    //             $report->getOrderBy('priority');
    //

    $criteria = [
        'SELECT' => [
            'glpi_tickets.priority',
            'glpi_tickets.date',
            'glpi_tickets.id',
            'glpi_tickets.id AS id2',
            'glpi_groups.name AS groupname',
        ],
        'FROM' => 'glpi_tickets',
        'LEFT JOIN' => [
            'glpi_groups_tickets' => [
                'ON' => [
                    'glpi_groups_tickets' => 'tickets_id',
                    'glpi_tickets' => 'id',
                    [
                        'AND' => [
                            'glpi_groups_tickets.type' => CommonITILActor::ASSIGN,
                        ],
                    ],
                ],
            ],
            'glpi_groups' => [
                'ON' => [
                    'glpi_groups_tickets' => 'groups_id',
                    'glpi_groups' => 'id',
                ],
            ],
        ],

        'WHERE' => [
            'glpi_tickets.status' => \Ticket::getNotSolvedStatusArray(),
            'glpi_tickets.is_deleted' => 0,
        ],
    ];

    $criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_tickets',
    );

    $criteria = $criteria + $report->getNewOrderBy('priority');

    $report->setSqlRequest($criteria);

    $report->execute();

}

$report->footer();
