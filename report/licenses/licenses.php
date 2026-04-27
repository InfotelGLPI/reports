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

use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\SoftwareWithLicenseCriteria;

$USEDBREPLICATE        = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

$dbu = new DbUtils();

//TRANS: The name of the report = Detailed license report
$report = new AutoReport(__('Detailed license report', 'reports'));

$license = new SoftwareWithLicenseCriteria($report, 'glpi_softwarelicenses.softwares_id');

$license->setSqlField('glpi_softwarelicenses.softwares_id');

$report->displayCriteriasForm();

// Form validate and only one software with license
if ($report->criteriasValidated()
    && $license->getParameterValue() >0 ) {

   $report->setSubNameAuto();

   $report->setColumns(["license" => _n('License', 'Licenses', 2),
                        "serial"  => __('Serial number'),
                        "nombre"  => _x('Quantity', 'Number'),
                        "type"    => __('Type'),
                        "buy"     => __('Purchase version'),
                        "used"    => __('Used version', 'reports'),
                        "expire"  => __('Expiration'),
                        "comment" => __('Comments'),
                        "name"    => __('Computer')]);

    $criteria = [
        'SELECT' => ['glpi_softwarelicenses.name AS license',
            'glpi_softwarelicenses.serial AS serial',
            'glpi_softwarelicenses.number AS nombre',
            'glpi_softwarelicensetypes.name AS type',
            'buyversion.name AS buy',
            'useversion.name AS used',
            'glpi_softwarelicenses.expire AS expire',
            'glpi_softwarelicenses.comment AS comment',
            'glpi_computers.id AS name',
        ],
        'FROM' => 'glpi_softwarelicenses',
        'LEFT JOIN'       => [
            'glpi_softwares' => [
                'ON' => [
                    'glpi_softwarelicenses' => 'softwares_id',
                    'glpi_softwares'          => 'id',
                ],
            ],
            'glpi_items_softwarelicenses' => [
                'ON' => [
                    'glpi_items_softwarelicenses' => 'softwarelicenses_id',
                    'glpi_softwarelicenses'          => 'id',
                ],
            ],
            'glpi_computers' => [
                'ON' => [
                    'glpi_computers'   => 'id',
                    'glpi_items_softwarelicenses'                  => 'items_id', [
                        'AND' => [
                            'glpi_items_softwarelicenses.itemtype' => 'Computer',
                        ],
                    ],
                ]
            ],
            'glpi_softwareversions AS buyversion' => [
                'ON' => [
                    'glpi_softwarelicenses' => 'softwareversions_id_buy',
                    'buyversion'          => 'id',
                ],
            ],
            'glpi_softwareversions AS useversion' => [
                'ON' => [
                    'glpi_softwarelicenses' => 'softwareversions_id_use',
                    'useversion'          => 'id',
                ],
            ],
            'glpi_softwarelicensetypes' => [
                'ON' => [
                    'glpi_softwarelicenses' => 'softwarelicensetypes_id',
                    'glpi_softwarelicensetypes'          => 'id',
                ],
            ],
        ],
        'WHERE' => ['glpi_softwares.is_deleted = 0',
            'glpi_softwares.is_template = 0'],
        'GROUPBY'   => ['license'],
        'ORDERBY'   => ['license'],
    ];
    $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
            'glpi_softwares'
        );

    $criteria['WHERE'] = $criteria['WHERE'] + $license->getNewSqlCriteriasRestriction();

    $report->setSqlRequest($criteria);

    $report->execute();

}

$report->footer();
