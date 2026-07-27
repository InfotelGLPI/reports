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

use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\Column;
use GlpiPlugin\Reports\ColumnDate;
use GlpiPlugin\Reports\ColumnInteger;
use GlpiPlugin\Reports\ColumnLink;
use GlpiPlugin\Reports\GroupCriteria;
use GlpiPlugin\Reports\LocationCriteria;

$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB;

$dbu = new DbUtils();

//TRANS: The name of the report = Printers
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_printers", READ);

$report = new AutoReport(__('Printers', 'reports'));

// Definition of the criteria
$grpcrit = new GroupCriteria($report, 'glpi_groups_items.groups_id', '', 'is_itemgroup');
$loccrit = new LocationCriteria($report, 'glpi_printers.locations_id');

//Display criterias form is needed
$report->displayCriteriasForm();


$report->setSubNameAuto();

$cols = [
    new ColumnLink(
        'id',
        __('Name'),
        'Printer',
        [
            'with_navigate' => true,
            'sorton' => 'glpi_printers.name'
        ]
    ),
    new Column('state', __('Status')),
    new Column('manu', __('Manufacturer')),
    new Column(
        'model',
        __('Model'),
        ['sorton' => 'glpi_manufacturers.name, glpi_printermodels.name']
    ),
    new Column('serial', __('Serial number')),
    new Column('otherserial', __('Inventory number')),
    new Column('immo_number', __('Immobilization number')),
    new ColumnDate(
        'buy_date',
        __('Date of purchase'),
        ['sorton' => 'glpi_infocoms.buy_date']
    ),
    new ColumnDate(
        'use_date',
        __('Startup date'),
        ['sorton' => 'glpi_infocoms.use_date']
    ),
    new ColumnInteger('last_pages_counter', __('Printed pages')),
    new ColumnLink('user', __('User'), 'User'),
    new ColumnLink(
        'groupe',
        __('Group'),
        'Group',
        ['sorton' => 'glpi_groups.name']
    ),
    //            new ColumnInteger('compgrp', __('Computers in the group', 'reports')),
    //            new ColumnInteger('usergrp', __('Users in the group', 'reports')),
    new ColumnLink(
        'location',
        __('Location'),
        'Location',
        ['sorton' => 'glpi_locations.completename']
    ),
    //            new ColumnInteger('comploc', __('Computers in the location', 'reports')),
    //            new ColumnInteger('userloc', __('Users in the location', 'reports'))
];

$report->setColumns($cols);

//   $compgrp = "SELECT COUNT(*)
//               FROM `glpi_computers`
//               WHERE `glpi_computers`.`groups_id`>0
//                     AND `glpi_computers`.`groups_id`=`glpi_printers`.`groups_id`";
//
//    $compgrp = [
//        'SELECT' => ['COUNT' => '*'],
//        'FROM' => 'glpi_computers',
//        'LEFT JOIN' => [
//            'glpi_groups_items AS items_computers' => [
//                'ON' => [
//                    'glpi_computers'    => 'id',
//                    'items_computers' => 'items_id', [
//                        'AND' => [
//                            'items_computers.itemtype' => 'Computer',
//                        ],
//                    ],
//                ]
//            ],
//            'glpi_groups_items AS items_printers' => [
//                'ON' => [
//                    'glpi_printers'    => 'id',
//                    'items_printers' => 'items_id', [
//                        'AND' => [
//                            'items_printers.itemtype' => 'Printer',
//                        ],
//                    ],
//                ]
//            ]
//        ],
//        'WHERE' => [
//            'items_computers.groups_id' => ['>', 0],
//            'items_computers.groups_id' => 'items_printers.groups_id',
//        ],
//    ];
//
//   $usergrp = "SELECT COUNT(*)
//               FROM `glpi_groups_users`
//               WHERE `glpi_groups_users`.`groups_id`>0
//                     AND `glpi_groups_users`.`groups_id`=`glpi_printers`.`groups_id`";
//
//    $usergrp = [
//        'SELECT' => ['COUNT' => '*'],
//        'FROM' => 'glpi_groups_users',
//        'WHERE' => [
//            ['AND'          => [
//                'glpi_groups_users.groups_id'  => ['>', 0],
//                'glpi_groups_users.groups_id'    => 'glpi_printers.groups_id',
//                ]
//            ]
//        ]
//    ];
//
//   $comploc = "SELECT COUNT(*)
//               FROM `glpi_computers`
//               WHERE `glpi_computers`.`locations_id`>0
//                     AND `glpi_computers`.`locations_id`=`glpi_printers`.`locations_id`";
//
//    $comploc = [
//        'SELECT' => ['COUNT' => '*'],
//        'FROM' => 'glpi_computers',
//        'WHERE' => [
//            ['AND'          => [
//                'glpi_computers.locations_id'  => ['>', 0],
//                'glpi_computers.locations_id'    => 'glpi_printers.locations_id',
//                ]
//            ]
//        ]
//    ];
//
//   $userloc = "SELECT COUNT(*)
//               FROM `glpi_users`
//               WHERE `glpi_users`.`locations_id`>0
//                     AND `glpi_users`.`locations_id`=`glpi_printers`.`locations_id`";

//   $sql = "SELECT `glpi_printers`.`id`, `glpi_printers`.`serial`, `glpi_printers`.`otherserial`,
//                  `glpi_printers`.`last_pages_counter`,
//                  `glpi_printermodels`.`name` AS model,
//                  `glpi_manufacturers`.`name` AS manu,
//                  `glpi_printers`.`users_id` AS user,
//                  `glpi_printers`.`groups_id` AS groupe,
//                  (".$compgrp.") AS compgrp,
//                  (".$usergrp.") AS usergrp,
//                  `glpi_locations`.`id` AS location,
//                  (".$comploc.") AS comploc,
//                  (".$userloc.") AS userloc,
//                  `glpi_infocoms`.`immo_number`, `glpi_infocoms`.`buy_date`,
//                  `glpi_infocoms`.`use_date`,
//                  `glpi_states`.`name` AS state
//           FROM `glpi_printers`
//           LEFT JOIN `glpi_printermodels`
//               ON (`glpi_printermodels`.`id`=`glpi_printers`.`printermodels_id`)
//           LEFT JOIN `glpi_manufacturers`
//               ON (`glpi_manufacturers`.`id`=`glpi_printers`.`manufacturers_id`)
//           LEFT JOIN `glpi_states` ON (`glpi_states`.`id`=`glpi_printers`.`states_id`)
//           LEFT JOIN `glpi_infocoms` ON (`glpi_infocoms`.`itemtype`='Printer'
//                                         AND `glpi_infocoms`.`items_id`=`glpi_printers`.`id`)
//           LEFT JOIN `glpi_locations` ON (`glpi_locations`.`id`=`glpi_printers`.`locations_id`)
//           LEFT JOIN `glpi_groups` ON (`glpi_groups`.`id`=`glpi_printers`.`groups_id`) ".
//           $dbu->getEntitiesRestrictRequest('WHERE', 'glpi_printers').
//           $report->addSqlCriteriasRestriction().
//           $report->getOrderBy('groupe');

$criteria = [
    'SELECT' => [
        'glpi_printers.id',
        'glpi_printers.serial',
        'glpi_printers.otherserial',
        'glpi_printers.last_pages_counter',
        'glpi_printermodels.name AS model',
        'glpi_manufacturers.name AS manu',
        'glpi_printers.users_id AS user',
        'glpi_groups.name AS groupe',
        //            'glpi_printers.compgrp',
        //            'glpi_printers.usergrp',
        //            'glpi_printers.comploc',
        'glpi_locations.id AS location',
        //            'glpi_printers.userloc',
        'glpi_infocoms.immo_number',
        'glpi_infocoms.buy_date',
        'glpi_infocoms.use_date',
        'glpi_states.name AS state',
    ],
    'FROM' => 'glpi_printers',
    'LEFT JOIN' => [
        'glpi_printermodels' => [
            'ON' => [
                'glpi_printers' => 'printermodels_id',
                'glpi_printermodels' => 'id',
            ],
        ],
        'glpi_manufacturers' => [
            'ON' => [
                'glpi_printers' => 'manufacturers_id',
                'glpi_manufacturers' => 'id',
            ],
        ],
        'glpi_states' => [
            'ON' => [
                'glpi_printers' => 'states_id',
                'glpi_states' => 'id',
            ],
        ],
        'glpi_infocoms' => [
            'ON' => [
                'glpi_printers' => 'id',
                'glpi_infocoms' => 'items_id',
                [
                    'AND' => [
                        'glpi_infocoms.itemtype' => 'Printer',
                    ],
                ],
            ],
        ],
        'glpi_locations' => [
            'ON' => [
                'glpi_printers' => 'locations_id',
                'glpi_locations' => 'id',
            ],
        ],
        'glpi_groups_items' => [
            'ON' => [
                'glpi_printers' => 'id',
                'glpi_groups_items' => 'items_id',
                [
                    'AND' => [
                        'glpi_groups_items.itemtype' => 'Printer',
                    ],
                ],
            ],
        ],
        'glpi_groups' => [
            'ON' => [
                'glpi_groups_items' => 'groups_id',
                'glpi_groups' => 'id',
            ],
        ],
    ],
    'WHERE' => [
        'glpi_printers.is_deleted' => 0,
        'glpi_printers.is_template' => 0,
    ],
    'GROUPBY' => ['groupe'],
];

$criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
        'glpi_printers'
    );

$criteria['WHERE'] = $criteria['WHERE'] + $report->addNewSqlCriteriasRestriction();

$criteria = $criteria + $report->getNewOrderBy('groupe');

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
