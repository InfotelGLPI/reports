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

use Glpi\DBAL\QuerySubQuery;
use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDateTime;
use GlpiPlugin\Reports\ColumnInteger;
use GlpiPlugin\Reports\DropdownCriteria;

//	Options for GLPI 0.71 and newer : need slave db to access the report
$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1;

// Initialization of the variables
global $DB;

$dbu = new DbUtils();

// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_statticketsbyentity", READ);

// The ticket counts and the oldest/newest ticket dates published below are restricted to the
// entity tree only, a boundary wider than the ITIL visibility perimeter: a profile limited to
// its own tickets (READMY) or to those of its groups (READGROUP) was counting every ticket of
// its entities. The core builds that perimeter in Ticket::getCriteriaFromProfile(); replaying
// it is the only way a hand written query can honour it, as statticketsbypriority already
// does. The core returns an empty array both for READALL and for a profile that may see no
// ticket at all, so the two cases are told apart by the READALL check.
$visibility_criteria = Ticket::getCriteriaFromProfile();
if (!Session::haveRight('ticket', Ticket::READALL) && !isset($visibility_criteria['WHERE'])) {
    throw new AccessDeniedHttpException();
}

$report = new AutoReport(__('Helpdesk requesters and tickets by entity', 'reports'));

//Report's search criterias
$prof = new DropdownCriteria(
    $report,
    'profiles_id',
    'glpi_profiles',
    __('Profile'),
);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();

    //Names of the columns to be displayed
    $cols = [new Column(
        'name',
        __('Entity'),
        ['sorton' => '`glpi_entities`.`completename`'],
    ),
        new ColumnInteger(
            'nbusers',
            __('Users count', 'reports'),
            ['withtotal' => true,
                'sorton'    => 'nbusers'],
        ),
        new ColumnInteger(
            'number',
            __('Tickets count', 'reports'),
            ['withtotal' => true,
                'sorton'    => 'number'],
        ),
        new ColumnDateTime(
            'mindate',
            __('Older', 'reports'),
            ['sorton' => 'mindate'],
        ),
        new ColumnDateTime(
            'maxdate',
            __('Newer', 'reports'),
            ['sorton' => 'maxdate'],
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
        'glpi_profiles_users',
    );

    $criteria = [
        'SELECT' => ['glpi_entities.completename AS name',
            new QuerySubQuery(
                $criteria_init,
                'nbusers',
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
    ];

    $criteria['WHERE'][] = getEntitiesRestrictCriteria(
        'glpi_entities',
    );

    // The WHERE fragments the core builds reference glpi_tickets by its table name, which is
    // exactly how the LEFT JOIN above names it, and the tu/gt aliases are free here.
    if (isset($visibility_criteria['LEFT JOIN'])) {
        $criteria['LEFT JOIN'] += $visibility_criteria['LEFT JOIN'];
    }
    if (isset($visibility_criteria['WHERE'])) {
        $criteria['WHERE'][] = $visibility_criteria['WHERE'];
    }

    $criteria = $criteria + $report->getNewOrderBy('name');

    $report->setSqlRequest($criteria);

    $report->execute(['withtotal' => true]);


}

$report->footer();
