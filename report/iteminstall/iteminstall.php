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

use Glpi\DBAL\QueryExpression;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 1;

// Initialization of the variables
global $DB;

$dbu = new DbUtils();

//TRANS: The name of the report = Time before equipment start-up
$report = new PluginReportsAutoReport(__('Time before equipment start-up', 'reports'));

//Report's search criterias
$date = new PluginReportsDateIntervalCriteria($report, 'buy_date');

$ignored = ['Cartridge', 'CartridgeItem', 'Consumable', 'ConsumableItem', 'Software', 'Line',
    'Certificate', 'Appliance', 'Domain', 'Item_DeviceSimcard', 'SoftwareLicense'];

$type = new PluginReportsItemTypeCriteria($report, 'itemtype', '', 'infocom_types', $ignored);

$budg = new PluginReportsDropdownCriteria($report, 'budgets_id', 'glpi_budgets', __('Budget'));

//Display criterias form is needed
$report->displayCriteriasForm();

$display_type = Search::HTML_OUTPUT;

//If criterias have been validated
if ($report->criteriasValidated()) {
    $report->setSubNameAuto();
    $title    = $report->getFullTitle();
    $itemtype = $type->getParameterValue();

    if ($itemtype && $itemtype != "all") {
        $types = [$itemtype];
    } else {
        $types = [];

        $criteria = [
            'SELECT' => ['itemtype'],
            'DISTINCT'        => true,
            'FROM' => 'glpi_infocoms',
            'WHERE' => []
        ];
        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                'glpi_infocoms'
            );

        $criteria['WHERE'] = $criteria['WHERE'] + $date->getNewSqlCriteriasRestriction();

        $criteria['WHERE'] = $criteria['WHERE'] + $budg->getNewSqlCriteriasRestriction();

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            $types[] = $data['itemtype'];
        }
    }

    $result = [];
    foreach ($types as $type) {
        if (!class_exists($type)) {
            continue;
        }
        $item  = new $type();
        $table = $item->getTable();

        // Total of buy equipment
        $criteria = [
            'SELECT' => ['COUNT' => $table.'.id AS cpt'],
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
                    ]
                ],
            ],
            'WHERE' => []
        ];
        if ($item->maybeDeleted()) {
            $criteria['WHERE'] = $criteria['WHERE'] + ['is_deleted' => 0];
        }
        if ($item->maybeTemplate()) {
            $criteria['WHERE'] = $criteria['WHERE'] + ['is_template' => 0];
        }
        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                $table
            );

        $criteria['WHERE'] = $criteria['WHERE'] + $date->getNewSqlCriteriasRestriction();

        $criteria['WHERE'] = $criteria['WHERE'] + $budg->getNewSqlCriteriasRestriction();

        $iterator = $DB->request($criteria);

        foreach ($iterator as $data) {
            $result[$type]['buy'] = $data['cpt'];
        }

        for ($deb = 0 ; $deb < 12 ; $deb = $fin) {
            $fin = $deb + 2;
            if ($deb) {
                $criteria['WHERE'] = $criteria['WHERE'] + ['use_date' => ['>=', new QueryExpression("DATE_ADD(".$DB->quoteName("buy_date").", INTERVAL $deb MONTH)")]];
            }
            if ($fin) {
                $criteria['WHERE'] = $criteria['WHERE'] + ['use_date' => ['<', new QueryExpression("DATE_ADD(".$DB->quoteName("buy_date").", INTERVAL $fin MONTH)")]];
            }
            $iterator = $DB->request($criteria);
            foreach ($iterator as $data) {
                $result[$type]["$deb-$fin"] = $data['cpt'];
            }
        }
        $criteria['WHERE'] = $criteria['WHERE'] + [
                'OR' => [
                    ['use_date' => ['<', new QueryExpression("DATE_ADD(".$DB->quoteName("buy_date").", INTERVAL 12 MONTH)")]],
                    ['use_date' => 'NULL'],
                ],
        ];

        $iterator = $DB->request($criteria);
        foreach ($iterator as $data) {
            $result[$type]['12+'] = $data['cpt'];
        }
    }
    /*
       if ($display_type == Search::HTML_OUTPUT) {
             echo "<div class='center'><table class='tab_cadre_fixe'>";
       //      echo "<tr><th>$title</th></tr>\n";
             echo "</table></div>\n";
       }
    */
    $nbres = count($result);
    if ($nbres > 0) {
        if ($nbres > 1) {
            $nbrows = $nbres * 2 + 2;
            $result['total'] = [];
            reset($result);
            foreach (next($result) as $key => $val) {
                $result['total'][$key] = 0;
            }
        } else {
            $nbrows = 2;
        }
        $nbcols = 9;
        echo Search::showHeader($display_type, $nbrows, $nbcols, true);
        echo Search::showNewLine($display_type);
        $numcol = 1;
        echo Search::showHeaderItem($display_type, __('Item type'), $numcol);
        echo Search::showHeaderItem($display_type, __('Total'), $numcol);
        echo Search::showHeaderItem($display_type, '0-1', $numcol);
        echo Search::showHeaderItem($display_type, '2-3', $numcol);
        echo Search::showHeaderItem($display_type, '4-5', $numcol);
        echo Search::showHeaderItem($display_type, '6-7', $numcol);
        echo Search::showHeaderItem($display_type, '8-9', $numcol);
        echo Search::showHeaderItem($display_type, '10-11', $numcol);
        echo Search::showHeaderItem($display_type, '12+', $numcol);
        echo Search::showEndLine($display_type);

        $row_num = 1;
        foreach ($result as $itemtype => $row) {
            if ($itemtype == 'total') {
                $name = __('Total');

            } elseif ($item = $dbu->getItemForItemtype($itemtype)) {
                $name = $item->getTypeName();

            } else {
                continue;
            }

            $numcol = 1;
            echo Search::showNewLine($display_type);
            echo Search::showItem($display_type, $name, $numcol, $row_num, "class='b'");
            $labels = [];
            $series = [];
            foreach ($row as $ref => $val) {
                $val = $result[$itemtype][$ref];
                echo Search::showItem(
                    $display_type,
                    ($val ? $val : ''),
                    $numcol,
                    $row_num,
                    "class='right'"
                );
                if ($itemtype != 'total' && isset($result['total'])) {
                    $result['total'][$ref] += $val;
                }
            }
            echo Search::showEndLine($display_type);
            $row_num++;

            $numcol = 1;
            echo Search::showNewLine($display_type);
            echo Search::showItem($display_type, '', $numcol, $row_num);
            foreach ($row as $ref => $val) {
                $val = $result[$itemtype][$ref];
                $buy = $result[$itemtype]['buy'];
                if (($ref == 'buy') || ($buy == 0) || ($val == 0)) {
                    $tmp = '';
                } else {
                    $tmp = round($val * 100 / $buy, 0) . "%";
                }
                echo Search::showItem($display_type, $tmp, $numcol, $row_num, "class='right'");
            }
            echo Search::showEndLine($display_type);
            $row_num++;
        }

        $stat = new Stat();
        $stat->displayPieGraph($title, $labels, $series);
        if ($display_type == Search::HTML_OUTPUT) {
            $row = array_pop($result); // Last line : total or single type
            unset($row['buy']);
        }
    } else {
        $nbrows = 1;
        $nbcols = 1;
        echo Search::showHeader($display_type, $nbrows, $nbcols, true);
        echo Search::showNewLine($display_type);
        $num = 1;
        echo Search::showHeaderItem($display_type, __s('No results found'), $num);
        echo Search::showEndLine($display_type);
    }
    echo Search::showFooter($display_type, $title);
}
if ($display_type == Search::HTML_OUTPUT) {
    Html::footer();
}
