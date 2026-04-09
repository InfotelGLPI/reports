<?php

/**
 * -------------------------------------------------------------------------
 * LICENSE
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
 * @package   reports
 * @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 * @copyright Copyright (c) 2009-2026 Reports plugin team
 * @license   AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://github.com/InfotelGLPI/reports
 * @link      http://www.glpi-project.org/
 * @since     2009
 * --------------------------------------------------------------------------
 */

use Glpi\DBAL\QueryExpression;
use Glpi\DBAL\QueryUnion;

$USEDBREPLICATE = 1;
$DBCONNECTION_REQUIRED = 0;

global $DB, $CFG_GLPI;


$report = new PluginReportsAutoReport(__('List of equipments by location', 'reports'));

$loc = new PluginReportsLocationCriteria($report);

$ignored = [
    'Cartridge',
    'CartridgeItem',
    'Consumable',
    'ConsumableItem',
    'Software',
    'Line',
    'Certificate',
    'Appliance',
    'Domain',
    'Item_DeviceSimcard',
    'SoftwareLicense'
];

$report->setColumns(
    [
        new PluginReportsColumnType('itemtype', __('Type'), $ignored),
        new PluginReportsColumnTypeLink(
            'items_id',
            __('Item'),
            'itemtype',
            ['with_comment' => 1]
        ),
        new PluginReportsColumn('statename', __('Status')),
        new PluginReportsColumn('serial', __('Serial number')),
        new PluginReportsColumn('otherserial', __('Inventory number')),
        new PluginReportsColumnModelType(
            'models_id',
            __('Model'),
            'itemtype',
            ['with_comment' => 1]
        ),
        new PluginReportsColumnTypeType(
            'types_id',
            __('Type'),
            'itemtype',
            ['with_comment' => 1]
        )
    ]
);

//Display criterias form is needed
$report->displayCriteriasForm();

//If criterias have been validated
if ($report->criteriasValidated()
    && $loc->getParameterValue() != 0) {

    $report->setSubNameAuto();

    $queries[] = getSqlSubRequest("Computer", $loc, new Computer());
    foreach ($CFG_GLPI["infocom_types"] as $itemtype) {
        $obj = new $itemtype();
        if ($obj->isField('locations_id') && ($itemtype != "Computer")) {
            $queries[] = getSqlSubRequest($itemtype, $loc, $obj);
        }
    }

    $union = new QueryUnion($queries, true);

    $req = ['FROM' => $union];

    $report->setSqlRequest($req);

    $report->execute();

} else {
    echo "<div class='alert alert-danger center'>" . __('Location not selected', 'reports') . "</div>";
    Html::footer();
}


function getSqlSubRequest($itemtype, $loc, $obj)
{
    $table = getTableForItemType($itemtype);

    $criteria = [
        'SELECT' => [
            new QueryExpression("'$itemtype' AS itemtype"),
            $table . '.id AS items_id',
            $table . '.locations_id',
        ],
        'FROM' => $table,
        'LEFT JOIN' => [],
        'WHERE' => [],
    ];

    $models_id = getForeignKeyFieldForTable(getTableForItemType($itemtype . 'Model'));
    $types_id = getForeignKeyFieldForTable(getTableForItemType($itemtype . 'Type'));

    $fields = [
        'name' => 'name',
        'serial' => 'serial',
        'otherserial' => 'otherserial',
        'states_id' => 'states_id',
        $models_id => 'models_id',
        $types_id => 'types_id'
    ];

    foreach ($fields as $field => $alias) {
        if ($obj->isField($field)) {
            if ($field == 'states_id') {
                $criteria['SELECT'] = array_merge($criteria['SELECT'],['glpi_states.name as statename']);

                $criteria['LEFT JOIN'] = $criteria['LEFT JOIN'] + [
                        'glpi_states' => [
                            'ON' => [
                                $table => 'states_id',
                                'glpi_states' => 'id',
                            ],
                        ]
                    ];
            } else {
                $criteria['SELECT'] = array_merge($criteria['SELECT'],[$table . '.' . $field . ' AS ' . $alias]);
            }
        } else {
            $criteria['SELECT'] = array_merge($criteria['SELECT'],[new QueryExpression("' ' AS $alias")]);
        }
    }

    if ($obj->isEntityAssign()) {
        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                $table
            );
    }

    if ($obj->maybeTemplate()) {
        $criteria['WHERE'] = $criteria['WHERE'] + ['is_template' => 0];
    }

    if ($obj->maybeDeleted()) {
        $criteria['WHERE'] = $criteria['WHERE'] + ['is_deleted' => 0];
    }

    $criteria['WHERE'] = $criteria['WHERE'] + $loc->getNewSqlCriteriasRestriction();

    return $criteria;
}
