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

use Glpi\DBAL\QuerySubQuery;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\TextCriteria;

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

//TRANS: The name of the report = Detailed report of software installation by status
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_softnotinstalled", READ);

$report = new AutoReport(__('Detailed report of software installation by status', 'reports'));
$soft   = new TextCriteria($report, 'software', _n('Software', 'Software', 1));
$soft->setSqlField('glpi_softwares.name');

$report->displayCriteriasForm();

// Form validate and only one software with license
if ($report->criteriasValidated()) {

   $report->setSubNameAuto();

   $report->setColumns([new ColumnLink('computer', __('Computer'),'Computer',
                                                    ['sorton' => 'glpi_computers.name']),
                        new Column('operatingsystems', __('Operating system'),
                                                ['sorton' => 'operatingsystems']),
                        new Column('state', __('Status'), ['sorton' => 'state']),
                        new Column('entity', __('Entity'),
                                                ['sorton' => 'entity,location']),
                        new Column('location',
                                                sprintf(__('%1$s - %2$s'), __('Location'),
                                                         __('Computer')),
                                                ['sorton' => 'location'])]);

   // Sub-request: computers that DO have (the searched) software installed.
   // The software text criteria and the entity restriction both apply here.
   $sub_where = getEntitiesRestrictCriteria('glpi_computers');
   $soft_restriction = $soft->getNewSqlCriteriasRestriction();
   if (is_array($soft_restriction)) {
       $sub_where = $sub_where + $soft_restriction;
   }

   $subquery = new QuerySubQuery([
       'SELECT'     => 'glpi_computers.id',
       'FROM'       => 'glpi_softwares',
       'INNER JOIN' => [
           'glpi_softwareversions' => [
               'ON' => [
                   'glpi_softwareversions' => 'softwares_id',
                   'glpi_softwares'        => 'id',
               ],
           ],
           'glpi_items_softwareversions' => [
               'ON' => [
                   'glpi_items_softwareversions' => 'softwareversions_id',
                   'glpi_softwareversions'       => 'id',
               ],
           ],
           'glpi_computers' => [
               'ON' => [
                   'glpi_items_softwareversions' => 'items_id',
                   'glpi_computers'              => 'id',
                   [
                       'AND' => [
                           'glpi_items_softwareversions.itemtype' => 'Computer',
                       ],
                   ],
               ],
           ],
       ],
       'WHERE' => $sub_where,
   ]);

   $criteria = [
       'SELECT'    => [
           'glpi_computers.id AS computer',
           'glpi_states.name AS state',
           'glpi_operatingsystems.name AS operatingsystems',
           'glpi_locations.completename AS location',
           'glpi_entities.completename AS entity',
       ],
       'FROM'      => 'glpi_computers',
       'LEFT JOIN' => [
           'glpi_states' => [
               'ON' => [
                   'glpi_states'    => 'id',
                   'glpi_computers' => 'states_id',
               ],
           ],
           'glpi_items_operatingsystems' => [
               'ON' => [
                   'glpi_items_operatingsystems' => 'items_id',
                   'glpi_computers'              => 'id',
                   [
                       'AND' => [
                           'glpi_items_operatingsystems.itemtype' => 'Computer',
                       ],
                   ],
               ],
           ],
           'glpi_operatingsystems' => [
               'ON' => [
                   'glpi_operatingsystems'       => 'id',
                   'glpi_items_operatingsystems' => 'operatingsystems_id',
               ],
           ],
           'glpi_locations' => [
               'ON' => [
                   'glpi_locations' => 'id',
                   'glpi_computers' => 'locations_id',
               ],
           ],
           'glpi_entities' => [
               'ON' => [
                   'glpi_entities'  => 'id',
                   'glpi_computers' => 'entities_id',
               ],
           ],
       ],
       'WHERE' => [
           'glpi_computers.is_template' => 0,
           'glpi_computers.is_deleted'  => 0,
           'NOT' => [
               'glpi_computers.id' => $subquery,
           ],
       ],
   ];

   $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria('glpi_computers');

   $criteria = $criteria + $report->getNewOrderBy('computer', true);

   $report->setSqlRequest($criteria);
   $report->execute();
}

$report->footer();
