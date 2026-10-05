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

use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use GlpiPlugin\Reports\AutoReport;
use GlpiPlugin\Reports\DateIntervalCriteria;
use GlpiPlugin\Reports\DropdownCriteria;
use GlpiPlugin\Reports\ItemTypeCriteria;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1;

// Initialization of the variables
global $DB, $CFG_GLPI;

$dbu = new DbUtils();

//TRANS: The name of the report = Time before equipment start-up
// Defense in depth: enforce the report right on page load, not only inside AutoReport::execute().
Session::checkRight("plugin_reports_iteminstall", READ);

// Every figure of this report is read from glpi_infocoms -- purchase date, budget, start-up delay
// -- and the core gates those behind its own dedicated "infocom" right, which is deliberately kept
// separate from the read right on the assets themselves. The plugin right must not stand in for it:
// report/infocom/infocom.php and report/searchinfocom/searchinfocom.php enforce the same rule on
// the same data. There is nothing left to display without it, so the whole page is gated.
Session::checkRight(Infocom::$rightname, READ);

$report = new AutoReport(__('Time before equipment start-up', 'reports'));

//Report's search criterias
$date = new DateIntervalCriteria($report, 'buy_date');

$ignored = ['Cartridge', 'CartridgeItem', 'Consumable', 'ConsumableItem', 'Software', 'Line',
    'Certificate', 'Appliance', 'Domain', 'Item_DeviceSimcard', 'SoftwareLicense'];

$type = new ItemTypeCriteria($report, 'itemtype', '', 'infocom_types', $ignored);

$budg = new DropdownCriteria($report, 'budgets_id', 'glpi_budgets', __('Budget'));

//Display criterias form is needed
$report->displayCriteriasForm();

$display_type = Search::HTML_OUTPUT;

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();
    $title    = $report->getFullTitle();
    $itemtype = $type->getParameterValue();

    // getParameterValue() returns request-controlled input; revalidate it against the
    // same allow-list that feeds the dropdown (CFG_GLPI['infocom_types'] minus the ignored
    // types) before it reaches the "new $type()" sink below, which only guards with
    // class_exists(). Mirrors report/infocom/infocom.php. An invalid itemtype falls back to
    // the "all" branch.
    $allowed_types = array_diff($CFG_GLPI['infocom_types'], $ignored);
    if ($itemtype && $itemtype != "all" && in_array($itemtype, $allowed_types, true)) {
        $types = [$itemtype];
    } else {
        $types = [];

        $criteria = [
            'SELECT' => ['itemtype'],
            'DISTINCT'        => true,
            'FROM' => 'glpi_infocoms',
            'WHERE' => [],
        ];
        $criteria['WHERE'][] = getEntitiesRestrictCriteria(
            'glpi_infocoms',
        );

        $criteria['WHERE'][] = $date->getNewSqlCriteriasRestriction();

        $criteria['WHERE'][] = $budg->getNewSqlCriteriasRestriction();

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            $types[] = $data['itemtype'];
        }
    }

    $result = [];
    foreach ($types as $type) {
        // The "all" branch above collects the itemtypes straight out of glpi_infocoms, so the
        // list is driven by the data, not by what the session may read: without the canView()
        // below the plugin right alone published the purchase counts and amounts of every
        // asset family, including the ones the profile has no read right on. infocom.php
        // applies the same rule on the same itemtype list.
        if (!class_exists($type) || !is_a($type, CommonDBTM::class, true)) {
            continue;
        }
        $item  = new $type();
        if (!$item->canView()) {
            continue;
        }
        $table = $item->getTable();

        // Total of buy equipment
        $criteria = [
            'SELECT' => ['COUNT' => $table . '.id AS cpt'],
            'FROM' => $table,
            'INNER JOIN'       => [
                'glpi_infocoms' => [
                    'ON' => [
                        $table   => 'id',
                        'glpi_infocoms' => 'items_id', [
                            'AND' => [
                                'glpi_infocoms.itemtype' => $type,
                            ],
                        ],
                    ],
                ],
            ],
            'WHERE' => [],
        ];
        if ($item->maybeDeleted()) {
            $criteria['WHERE'][] = ['is_deleted' => 0];
        }
        if ($item->maybeTemplate()) {
            $criteria['WHERE'][] = ['is_template' => 0];
        }
        $criteria['WHERE'][] = getEntitiesRestrictCriteria(
            $table,
        );

        $criteria['WHERE'][] = $date->getNewSqlCriteriasRestriction();

        $criteria['WHERE'][] = $budg->getNewSqlCriteriasRestriction();

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            $result[$type]['buy'] = $data['cpt'];
        }

        // Keep a pristine copy of the criteria built above: each slice below appends its own
        // date bounds, and the array was never reset between two iterations. The previous
        // slice's DATE_ADD conditions therefore survived and were ANDed with the current ones;
        // the slices being disjoint by construction, the conjunction became unsatisfiable from
        // the second iteration on and every bucket after 0-2 reported zero -- the trailing
        // "12+" count included, since it reused the same accumulated array.
        $base_criteria = $criteria;

        for ($deb = 0 ; $deb < 12 ; $deb = $fin) {
            $fin = $deb + 2;
            $criteria = $base_criteria;
            if ($deb) {
                $criteria['WHERE'][] = ['use_date' => ['>=', new QueryExpression("DATE_ADD(" . $DB->quoteName("buy_date") . ", INTERVAL $deb MONTH)")]];
            }
            if ($fin) {
                $criteria['WHERE'][] = ['use_date' => ['<', new QueryExpression("DATE_ADD(" . $DB->quoteName("buy_date") . ", INTERVAL $fin MONTH)")]];
            }
            $iterator = $DB->request($criteria);
            foreach ($iterator as $data) {
                $result[$type]["$deb-$fin"] = $data['cpt'];
            }
        }
        $criteria = $base_criteria;
        $criteria['WHERE'][] = [
            'OR' => [
                ['use_date' => ['<', new QueryExpression("DATE_ADD(" . $DB->quoteName("buy_date") . ", INTERVAL 12 MONTH)")]],
                ['use_date' => 'NULL'],
            ],
        ];

        $iterator = $DB->request($criteria);
        foreach ($iterator as $data) {
            $result[$type]['12+'] = $data['cpt'];
        }
    }
    $table = [
        'class'       => 'table',
        'header_rows' => [],
        'rows'        => [],
    ];
    $chart = '';
    $nbres = count($result);
    if ($nbres > 0) {
        if ($nbres > 1) {
            $result['total'] = [];
            reset($result);
            foreach (next($result) as $key => $val) {
                $result['total'][$key] = 0;
            }
        }
        $table['header_rows'][] = ['cells' => array_map(
            static fn($title) => ['value' => $title],
            [__('Item type'), __('Total'), '0-1', '2-3', '4-5', '6-7', '8-9', '10-11', '12+'],
        )];

        foreach ($result as $itemtype => $row) {
            if ($itemtype == 'total') {
                $name = __('Total');

            } elseif ($item = $dbu->getItemForItemtype($itemtype)) {
                $name = $item->getTypeName();

            } else {
                continue;
            }

            $count_cells   = [['value' => $name, 'class' => 'b']];
            $percent_cells = [['value' => '']];
            foreach ($row as $ref => $val) {
                $val = $result[$itemtype][$ref];
                $count_cells[] = ['value' => $val ? $val : '', 'class' => 'right'];
                if ($itemtype != 'total' && isset($result['total'])) {
                    $result['total'][$ref] += $val;
                }

                $buy = $result[$itemtype]['buy'];
                if (($ref == 'buy') || ($buy == 0) || ($val == 0)) {
                    $tmp = '';
                } else {
                    $tmp = round($val * 100 / $buy, 0) . "%";
                }
                $percent_cells[] = ['value' => $tmp, 'class' => 'right'];
            }
            // The percentages are computed against the "buy" count of the same row, which is
            // complete by now for the total row as well (it comes last).
            $table['rows'][] = ['class' => 'tab_bg_2', 'cells' => $count_cells];
            $table['rows'][] = ['class' => 'tab_bg_1', 'cells' => $percent_cells];
        }

        // Pie chart of the last line (total, or the single type): the labels and series handed
        // to the chart used to be reset inside the loop above and never filled, so the chart
        // was always drawn empty.
        $row = end($result);
        unset($row['buy']);
        $labels = [];
        $series = [];
        foreach ($row as $ref => $val) {
            $labels[] = $ref;
            $series[] = ['name' => $ref, 'data' => (int) $val];
        }
        $stat  = new Stat();
        $chart = (string) $stat->displayPieGraph($title, $labels, $series, [], false);
    } else {
        $table['header_rows'][] = ['cells' => [['value' => __('No results found')]]];
    }

    TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', [
        'tables' => [$table],
        'chart'  => $chart,
    ]);
}
if ($display_type == Search::HTML_OUTPUT) {
    Html::footer();
}
