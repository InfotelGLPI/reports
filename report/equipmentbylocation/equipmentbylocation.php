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
use GlpiPlugin\Reports\ColumnInteger;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_equipmentbylocation", READ);

$report = new AutoReport(__('Number of equipments by location', 'reports'));

$dbu = new DbUtils();

// The report aggregates the volumetry of six asset types, and the report right alone used to be
// enough to obtain it: nothing confronted the read right of Computer, Monitor, Printer,
// Peripheral, Phone or NetworkEquipment, although every sibling report of this plugin does.
// A profile deliberately denied the assets still got the exact count of each park, location by
// location. Build the columns, the projection and the derived sub-queries from the types the
// profile may actually read, so a denied type leaves no trace in the result at all -- it has no
// column, so no total can give it back by subtraction either.
$asset_types = [
    \Computer::class => ['alias' => 'comp', 'column' => 'computernumber',
        'table' => 'glpi_computers', 'label' => _n('Computer', 'Computers', 2)],
    \NetworkEquipment::class => ['alias' => 'net', 'column' => 'networknumber',
        'table' => 'glpi_networkequipments', 'label' => _n('Network', 'Networks', 2)],
    \Monitor::class => ['alias' => 'mon', 'column' => 'monitornumber',
        'table' => 'glpi_monitors', 'label' => _n('Monitor', 'Monitors', 2)],
    \Printer::class => ['alias' => 'pri', 'column' => 'printernumber',
        'table' => 'glpi_printers', 'label' => _n('Printer', 'Printers', 2)],
    \Peripheral::class => ['alias' => 'per', 'column' => 'peripheralnumber',
        'table' => 'glpi_peripherals', 'label' => _n('Device', 'Devices', 2)],
    \Phone::class => ['alias' => 'pho', 'column' => 'phonenumber',
        'table' => 'glpi_phones', 'label' => _n('Phone', 'Phones', 2)],
];

$columns = [new Column('entity', __('Entity')),
    new Column('location', __('Location'))];

$select = ['glpi_entities.completename AS entity',
    'glpi_locations.completename AS location'];

$joins = [
    'glpi_entities' => [
        'ON' => [
            'glpi_entities'  => 'id',
            'glpi_locations' => 'entities_id',
        ],
    ],
];

foreach ($asset_types as $itemtype => $asset) {
    $item = new $itemtype();
    if (!$item->canView()) {
        continue;
    }

    $columns[] = new ColumnInteger($asset['column'], $asset['label']);
    $select[]  = $asset['alias'] . '.' . $asset['column'];
    // Table name, alias and column name all come from the static map above: no request value
    // ever reaches the expression, which is why it may be written as one.
    $joins[$asset['alias']] = [
        'TABLE' => new QueryExpression('(
                SELECT COUNT(*) AS ' . $asset['column'] . ', locations_id
                FROM ' . $asset['table'] . '
                WHERE is_deleted = 0 AND is_template = 0
                ' . $dbu->getEntitiesRestrictRequest(' AND ', $asset['table']) . '
                GROUP BY locations_id
            ) AS ' . $asset['alias']),
        'ON' => [
            $asset['alias']  => 'locations_id',
            'glpi_locations' => 'id',
        ],
    ];
}

if (count($columns) === 2) {
    // Not one of the six types is readable: the profile would only get the list of the
    // locations, which is not what this report is. Refuse rather than render an empty grid.
    throw new \Glpi\Exception\Http\AccessDeniedHttpException();
}

$report->setColumns($columns);

$criteria = [
    'SELECT' => $select,
    'FROM' => 'glpi_locations',
    'LEFT JOIN' => $joins,
    'WHERE' => [],
    'GROUPBY'   => ['glpi_locations.id'],
    'ORDERBY' => [
        'entity',
        'location',
    ],
];

// glpi_locations is recursive and getEntitiesRestrictCriteria() does not infer it from the
// table name: without the fourth argument, a location shared down from a parent entity was
// dropped here, and with it every piece of equipment attached to that location. Same
// reasoning as listgroups.php.
$criteria['WHERE'][] = getEntitiesRestrictCriteria(
    'glpi_locations',
    '',
    '',
    true,
);

$report->setSqlRequest($criteria);

$report->execute();

$report->footer();
