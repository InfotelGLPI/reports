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
 * @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel, Alexandre Delaunay
 * @copyright Copyright (c) 2009-2022 Reports plugin team
 * @license   AGPL License 3.0 or (at your option) any later version
 * http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @link      https://forge.glpi-project.org/projects/reports
 * @link      http://www.glpi-project.org/
 * @since     2009
 * --------------------------------------------------------------------------
 */

use Glpi\Application\View\TemplateRenderer;
use Glpi\Search\Output\HTMLSearchOutput;
use Glpi\Search\SearchEngine;

/**
 * Class to create, execute and display a new record
 * The class stores a collection of criterias and
 * manage :
 *   - criterias selection form
 *   - query executing using with criterias restriction
 *   - result display & export (HTML, PDF, CSV, SLK)
 **/
#[AllowDynamicProperties]
class PluginReportsAutoReport extends CommonDBTM
{
    public static $rightname = 'config';
    private $criterias = [];
    private $columns = [];
    private $group_by = [];
    private $columns_mapping = [];
    private $sql = "";
    private $name = "";
    private $subname = "";
    private $cpt = 0;
    private $title = '';


    public function __construct($title = '')
    {
        preg_match('@/(plugins|marketplace)/(.*)/report/(.*)/@', $_SERVER['REQUEST_URI'], $regs);
        $this->plug = $regs[2];
        $this->name = $regs[3];
        includeLocales($this->name, $this->plug);
        $this->setTitle($title);
    }


    //used only for export
    public static function getTable($classname = null)
    {
        return "glpi_configs";
    }

    //used only for export
    public function isEntityAssign()
    {
        return false;
    }

    //used only for export
    public static function getNameField()
    {
        return 'name';
    }

    //-------------- Getters ------------------//
    public function getCriterias()
    {
        return $this->criterias;
    }


    //-------------- Setters ------------------//

    /**
     * Set column mappings : when a column's value cannot be
     * displays as it is, but needs to be replaced by another one
     * DEPRECATED : should use PluginReportsColumnMap
     *
     * @param $columns_mappings array the columns new values
     **/
    public function setColumnsMappings($columns_mappings)
    {
        $this->columns_mapping = $columns_mappings;
    }


    /**
     * Defined "GROUP BY" columns
     * for output improvment
     * first line displayed in bold
     * next lines not displayed
     *
     * @param $columns
     **/
    public function setGroupBy($columns)
    {
        if (is_array($columns)) {
            $this->group_by = $columns;
        } else {
            $this->group_by = [$columns];
        }
    }

    /**
     * Defined "GROUP BY" columns
     * for output improvment
     * first line displayed in bold
     * next lines not displayed
     *
     * @param $columns
     **/
    public function setNewGroupBy($columns)
    {
        $this->group_by = $columns;
    }


    /**
     * Set columns names (label to be displayed)
     *
     * @param $columns array which contains
     *        sql column name => PluginReportsColumn object
     **/
    public function setColumns($columns)
    {
        $this->columns = [];
        foreach ($columns as $name => $column) {
            if ($column instanceof PluginReportsColumn) {
                $this->columns[$column->name] = $column;
            } else {
                // For compat with setColumnsNames - default text mode
                $this->columns[$name] = new PluginReportsColumn($name, $column);
            }
        }
    }


    /**
     * Set sql request to be executed
     * @param sql the sql request as a string
     **/
    public function setSqlRequest($sql)
    {
        $this->sql = $sql;
    }


    /**
     * Set report's name
     * @param name the name of the report
     **/
    public function setName($name)
    {
        [$this->plug, $this->name] = explode('.', $name, 2);
    }


    /**
     * Set report's Title
     *
     * @param $title the title of the report
     **/
    public function setTitle($title)
    {
        if ($title) {
            $this->title = $title;
        } else {
            $this->title = (isset($this->name)
                ? sprintf(__('%s'), $this->name)
                : __('Report', 'Reports', 1));
        }
    }


    /**
     * Get the report's title (main title + sub title from criteria)
     **/
    public function getFullTitle()
    {
        if ($this->subname) {
            return $this->title . " - " . $this->subname;
        }
        return $this->title;
    }


    /**
     * Set the report's subname
     *
     * @param - subname the report's subname to display
     **/
    public function setSubName($subname)
    {
        $this->subname = $subname;
    }


    /**
     * Generate automatically the report's subname
     **/
    public function setSubNameAuto()
    {
        $subname = "";
        $prefix = "";
        //Get all criteria's subnames and add it to the report's subname
        foreach ($this->criterias as $criteria) {
            if ($name = $criteria->getSubName()) {
                $subname .= $prefix . $name;
                $prefix = " - ";
            }
        }

        $this->subname = $subname;
    }


    //------------- Other -------------//

    /**
     * Indicates if the criteria's form is validated or not
     *
     * @return true if form is validated
     **/
    public function criteriasValidated()
    {
        return isset($_POST['find']);
    }


    /**
     * @param     $start
     * @param     $numrows
     * @param     $target
     * @param     $parameters
     * @param int $item_type_output
     * @param int $item_type_output_param
     */
    public static function printPager(
        $start,
        $numrows,
        $target,
        $parameters,
        $item_type_output = 0,
        $item_type_output_param = 0,
        $additional_info = ''
    ) {
        global $CFG_GLPI;

        $start = (int)$start;
        $numrows = (int)$numrows;
        $list_limit = (int)$_SESSION['glpilist_limit'];

        // Forward is the next step forward
        $forward = $start + $list_limit;

        // This is the end, my friend
        $end = $numrows - $list_limit;

        // Human readable count starts here

        $current_start = $start + 1;

        // And the human is viewing from start to end
        $current_end = $current_start + $list_limit - 1;
        if ($current_end > $numrows) {
            $current_end = $numrows;
        }

        // Empty case
        if ($current_end == 0) {
            $current_start = 0;
        }

        // Backward browsing
        if ($current_start - $list_limit <= 0) {
            $back = 0;
        } else {
            $back = $start - $list_limit;
        }

        // Print it
        echo "<div><table class='table align-middle'>";
        echo "<tr>";

        if (!str_contains($target, '?')) {
            $fulltarget = $target . "?" . $parameters;
        } else {
            $fulltarget = $target . "&" . $parameters;
        }
        // Back and fast backward button
        if (!$start == 0) {
            echo "<th class='left'>";
            echo "<a href='" . htmlescape($fulltarget) . "&amp;start=0' class='btn btn-sm btn-ghost-secondary me-2'
                  title=\"" . __s('Start') . "\" data-bs-toggle='tooltip' data-bs-placement='top'>";
            echo "<i class='ti ti-chevrons-left'></i>";
            echo "</a>";
            echo "<a href='" . htmlescape($fulltarget) . "&amp;start=$back' class='btn btn-sm btn-ghost-secondary me-2'
                  title=\"" . __s('Previous') . "\" data-bs-toggle='tooltip' data-bs-placement='top'>";
            echo "<i class='ti ti-chevron-left'></i>";
            echo "</a></th>";
        }

        // Print the "where am I?"
        echo "<td width='31%' class='tab_bg_2'>";
        Html::printPagerForm("$fulltarget&start=$start");
        echo "</td>";

        if (!empty($additional_info)) {
            echo "<td class='tab_bg_2'>";
            echo $additional_info;
            echo "</td>";
        }
        if (
            !empty($item_type_output)
            && isset($_SESSION["glpiactiveprofile"])
            && (Session::getCurrentInterface() == "central")
            && $numrows > 0
        ) {
            echo "<td class='tab_bg_2 responsive_hidden' width='30%'>";
            echo "<form method='GET' action='" . $_SERVER['REQUEST_URI'] . "' target='_blank'>\n";

            echo Html::hidden('item_type', ['value' => $item_type_output]);

            if (is_array($item_type_output_param)) {
                echo Html::hidden(
                    'item_type_param',
                    ['value' => Toolbox::prepareArrayForInput($item_type_output_param)]
                );
            }

            $parameters = trim($parameters, '&');
            if (!str_contains($parameters, 'start')) {
                $parameters .= "&start=$start";
            }

            $split = explode("&", $parameters);

            $count_split = count($split);
            for ($i = 0; $i < $count_split; $i++) {
                $pos = Toolbox::strpos($split[$i], '=');
                $length = Toolbox::strlen($split[$i]);
                echo Html::hidden(
                    Toolbox::substr($split[$i], 0, $pos),
                    ['value' => urldecode(Toolbox::substr($split[$i], $pos + 1))]
                );
            }

            Dropdown::showOutputFormat($item_type_output);
            Html::closeForm();
            echo "</td>";
        }

        echo "<td width='20%' class='b'>";
        //TRANS: %1$d, %2$d, %3$d are page numbers
        printf(__s('From %1$d to %2$d of %3$d'), $current_start, $current_end, $numrows);
        echo "</td>";

        // Forward and fast forward button
        if ($forward < $numrows) {
            echo "<th class='right'>";
            echo "<a href='" . htmlescape($fulltarget) . "&amp;start=$forward' class='btn btn-sm btn-ghost-secondary'
                  title=\"" . __s('Next') . "\" data-bs-toggle='tooltip' data-bs-placement='top'>
               <i class='ti ti-chevron-right'></i>";
            echo "</a>";
            echo "<a href='" . htmlescape($fulltarget) . "&amp;start=$end' class='btn btn-sm btn-ghost-secondary'
                  title=\"" . __s('End') . "\" data-bs-toggle='tooltip' data-bs-placement='top'>";
            echo "<i class='ti ti-chevrons-right'></i>";
            echo "</a>";
            echo "</th>";
        }
        // End pager
        echo "</tr></table></div>";
    }

    public static function showOutputFormat()
    {
        $values[Search::PDF_OUTPUT_LANDSCAPE] = __s('All pages in landscape PDF');
        $values[Search::PDF_OUTPUT_PORTRAIT] = __s('All pages in portrait PDF');
        $values[Search::CSV_OUTPUT] = __s('All pages in CSV');

        Dropdown::showFromArray('display_type', $values);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        echo Html::submit(_sx('button', 'Export'), ['name' => 'export', 'class' => 'btn btn-primary']);
    }

    /**
     * Execute the report
     *
     * @param $options   array
     **/
    public function execute($options = [])
    {
        global $DB, $HEADER_LOADED;

        $field = 'plugin_reports_' . $this->name;
        if ($this->plug != 'reports') {
            $field = 'plugin_reports_' . $this->plug . "_" . $this->name;
        }
        Session::checkRight($field, READ);

        // Require (for pager) when not called by displayCriteriasForm
        $this->manageCriteriasValues();

        if (isset($_POST['list_limit'])) {
            $_SESSION['glpilist_limit'] = $_POST['list_limit'];
            unset($_POST['list_limit']);
        }

        $limit = $_SESSION['glpilist_limit'];

        $output_type = Search::HTML_OUTPUT;
        if (isset($_GET["display_type"])) {
            $output_type = $_GET["display_type"];
        }

        $default_values["start"] = $start = 0;
        $default_values["id"] = $id = 0;
        $default_values["export"] = $export = false;

        foreach ($default_values as $key => $val) {
            if (isset($values[$key])) {
                $$key = $values[$key];
            }
        }
        $itemtype = self::class;
        // Set display type for export if define
        $output_type = $output_type ?? Search::HTML_OUTPUT;
        $output = SearchEngine::getOutputForLegacyKey($output_type);
        $is_html_output = $output instanceof HTMLSearchOutput;
        $html_output = '';

        if (isset($values["display_type"])) {
            $output_type = $values["display_type"];
        }
        $title = $this->title;
        if ($this->subname) {
            $title = sprintf(__('%1$s - %2$s'), $title, $this->subname);
        }

        if (is_array($this->sql)) {
            $res = $DB->request($this->sql);
            $numrows = ($res ? count($res) : 0);
        } else {
            $res = $DB->doQuery($this->sql);
            $numrows = ($res ? $DB->numrows($res) : 0);
        }

        if ($limit) {
            $start = (isset($_GET["start"]) ? intval($_GET["start"]) : 0);
            if ($start >= $numrows) {
                $start = 0;
            }
            if (($start > 0) || (($start + $limit) < $numrows)) {
                if (is_array($this->sql)) {
                    $criteria = $this->sql;
                    $criteria['LIMIT'] = $start . '' . $limit;
                    $res = $DB->request($criteria);
                } else {
                    $res = $DB->doQuery($this->sql . " LIMIT $start,$limit");
                }
            }
        } else {
            $start = 0;
        }

        if ($numrows == 0) {
            if (!$HEADER_LOADED) {
                Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
                Report::title();
            }
            echo "<div class='alert alert-danger center'>" . __('No results found') . "</div>";
            Html::footer();
        } elseif ($is_html_output) {
            if (!$HEADER_LOADED) {
                Html::header($title, $_SERVER['PHP_SELF'], "utils", "report");
                Report::title();
            }
            $param = "";
            foreach ($_POST as $key => $val) {
                if (is_array($val)) {
                    foreach ($val as $k => $v) {
                        echo Html::hidden($key . [$k], ['value' => $v]);
                        if (!empty($param)) {
                            $param .= "&";
                        }
                        $param .= $key . "[" . $k . "]=" . urlencode($v);
                    }
                } else {
                    echo Html::hidden($key, ['value' => $val]);
                    if (!empty($param)) {
                        $param .= "&";
                    }
                    $param .= "$key=" . urlencode($val);
                }
            }
            self::printPager($start, $numrows, $_SERVER['REQUEST_URI'], $param, "PluginReportsAutoReport");
        }

        if ($res && ($numrows > 0)) {
            if (!isset($_POST["display_type"]) || $is_html_output) {
                if (isset($options['withmassiveaction']) && class_exists($options['withmassiveaction'])) {
                    $massformid = 'massform' . $options['withmassiveaction'];
                    Html::openMassiveActionsForm($massformid);
                    Html::showMassiveActions(['container' => $massformid]);
                }
            }

            if (is_array($this->sql)) {
                $numrows = count($res);

                $nbcols = 0;
                foreach ($res as $row) {
                    $nbcols = count($row);
                    break;
                }
            } else {
                $nbcols = $DB->numFields($res);
                $numrows = $DB->numrows($res);
            }

            $end_display = $numrows;
            if (isset($_GET['export_all'])) {
                $start = 0;
                $end_display = $numrows;
            }

            if ($is_html_output) {
                $html_output .= $output::showHeader($end_display - $start + 1, $nbcols);
            }

            // fill $sqlcols with default sql query fields so we can validate $columns
            $sqlcols = [];
            $sqlvalues = [];
            if (is_array($this->sql)) {
                foreach ($res as $row) {
                    $sqlcols = array_keys($row);
                    break;
                }

                foreach ($res as $row) {
                    $sqlvalues[] = array_values($row);
                }
            } else {
                for ($i = 0; $i < $nbcols; $i++) {
                    $colname = $DB->fieldName($res, $i);
                    $sqlcols[] = $colname;
                }

                for ($row_num = 0; $row = $DB->fetchAssoc($res); $row_num++) {
                    $sqlvalues[] = array_values($row);
                }
            }

            $header_num = 1;
            if ($is_html_output) {
                $html_output .= $output::showNewLine();
            }
            $colsname = [];
            // if $columns is not empty, display $columns
            if (count($this->columns) > 0) {
                foreach ($this->columns as $colname => $column) {
                    // display only $columns that are valid
                    if (in_array($colname, $sqlcols)) {
                        if ($is_html_output) {
                            $html_output .= $column->showHtmlTitle($output, $header_num);
                        } else {
                            $headers[] = $column->showExportTitle($output, $header_num);
                        }
                        $colsname[$colname] = $column;
                    }
                }
            } else { // else display default columns from SQL query
                foreach ($sqlcols as $colname) {
                    $column = new PluginReportsColumn($colname, $colname);
                    if ($is_html_output) {
                        $html_output .= $column->showHtmlTitle($output, $header_num);
                    } else {
                        $headers[] = $column->showExportTitle();
                    }
                    $colsname[$colname] = $column;
                }
            }
            if ($is_html_output) {
                $html_output .= $output::showEndLine();
            }

            $list = [];
            $i = 0;

            foreach ($res as $k => $data) {
                $list[$i] = $data;
                $i++;
            }

            $row_num = 0;
            if (!empty($sqlvalues)) {
                for ($i = $start; ($i < $numrows) && ($i < $end_display); $i++) {
                    $row_num++;
                    $current_row = [];

                    $colnum = 0;

                    if ($is_html_output) {
                        $html_output .= $output::showNewLine($i % 2 === 1);
                    }

                    foreach ($colsname as $colname => $column) {
                        if ($is_html_output) {
                            $html_output .= $output::showItem($column->showValue($output_type, $list[$i]), $num, $row_num);
                        } else {
                            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $column->showValue($output_type, $list[$i])];
                        }
                    }

                    $rows[$row_num] = $current_row;
                    if ($is_html_output) {
                        $html_output .= $output::showEndLine(false);
                    }

                    if ($is_html_output) {
                        if (isset($options['withtotal']) && $options['withtotal']) {
                            $html_output .= $output::showNewLine();
                            foreach ($colsname as $colname => $column) {
                                $html_output .= $output::showItem(
                                    $column->showNewTotal($output_type, $num, $row_num),
                                    $num,
                                    $row_num
                                );
                            }

                            $html_output .= $output::showEndLine();
                        }
                    }
                }
            }

            if ($is_html_output) {
                Html::closeForm();
                $output::showFooter($title, $numrows);
            }

//            if (!isset($_POST["display_type"]) || $is_html_output) {
//                if (isset($options['withmassiveaction']) && class_exists($options['withmassiveaction'])) {
//                    Html::showMassiveActions(['container' => $massformid,
//                        'ontop'     => false]);
//                    Html::closeForm();
//                }
//                Html::footer();
//            }

            if ($is_html_output) {
                echo $html_output;
            } else {
                $params = [
                    'start' => 0,
                    'is_deleted' => 0,
                    'as_map' => 0,
                    'browse' => 0,
                    'unpublished' => 1,
                    'criteria' => [],
                    'metacriteria' => [],
                    'display_type' => 0,
                    'hide_controls' => true,
                ];

                $report_data = SearchEngine::prepareDataForSearch($itemtype, $params);
                $report_data = array_merge($report_data, [
                    'itemtype' => $itemtype,
                    'data' => [
                        'totalcount' => $numrows,
                        'count' => $numrows,
                        'search' => '',
                        'cols' => [],
                        'rows' => $rows,
                    ],
                ]);

                $colid = 0;
                foreach ($headers as $header) {
                    $report_data['data']['cols'][] = [
                        'name' => $header,
                        'itemtype' => $itemtype,
                        'id' => ++$colid,
                    ];
                }

                $output->displayData($report_data, []);
            }
        }
    }


    /**
     * Display a common search criterias form
     */
    public function displayCriteriasForm()
    {
        global $HEADER_LOADED;

        //Get criteria's values
        $this->manageCriteriasValues();

        //Display Html::header is output is HTML
        if (isset($_POST["display_type"]) && $_POST["display_type"] != Search::HTML_OUTPUT) {
            return;
        }
        if (!$HEADER_LOADED) {
            $title = $this->title;
            if ($this->subname) {
                $title = sprintf(__('%1$s - %2$s'), $title, $this->subname);
            }

            if (isStat($this->name)) {
                Html::header($title, $_SERVER['PHP_SELF'], "helpdesk", "stat");
                Stat::title();
            } else {
                Html::header(
                    $title,
                    $_SERVER['PHP_SELF'],
                    "tools",
                    "report"
                );
                Report::title();
            }
        }

        $field = 'plugin_reports_' . $this->name;
        if ($this->plug != 'reports') {
            $field = 'plugin_reports_' . $this->plug . "_" . $this->name;
        }
        Session::checkRight($field, READ);

        //Display form only if there're criterias
        if (!empty($this->criterias)) {
            echo "<div class='center'>";
            echo "<form method='post' name='form' action='" . $_SERVER['REQUEST_URI'] . "'>";
            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th colspan='6'>" . __('Search criteria', 'reports');

            //If form is validated, then display the bookmark button
            if ($this->criteriasValidated()) {
                //Add parameters to uri to be saved as bookmarks
                $_SERVER["REQUEST_URI"] = $this->buildBookmarkUrl();
                TemplateRenderer::getInstance()->render('pages/tools/savedsearch/save_button.html.twig', [
                    'type' => SavedSearch::SEARCH,
                    'itemtype' => (isStat($this->name) ? 'PluginReportsStat' : 'PluginReportsReport'),
                ]);
            }
            echo "</th></tr>\n";

            //Display each criteria's html selection item
            foreach ($this->criterias as $criteria) {
                $criteria->displayCriteria();
            }

            $this->closeColumn();

            echo "<tr class='tab_bg_2'><td colspan='4' class='center'>";
            echo Html::submit(_sx('button', 'Search'), [
                'name' => 'find',
                'class' => 'btn btn-primary'
            ]);
            echo "</td></tr>";
            echo "</table></div>";
            Html::closeForm();
        }
    }


    public function manageCriteriasValues()
    {
        foreach ($this->criterias as $criteria) {
            $criteria->manageCriteriaValues();
        }

        //If selectio form is validated, then stores it
        if (isset($_GET['find']) || isset($_POST['find'])) {
            $_POST['find'] = true;
        }
        // Order by
        if (isset($_GET['sort'])) {
            $_POST['sort'] = $_GET['sort'];
        }
        if (isset($_GET['order'])) {
            $_POST['order'] = $_GET['order'];
        }
    }


    /**
     * Append date and time restriction in an sql request
     * @param link with previous condition
     */
    public function addNewSqlCriteriasRestriction($link = 'AND')
    {
        $sql = [];
        //Get all criterias sql restriction criterias
        foreach ($this->criterias as $criteria) {
            $add = $criteria->getNewSqlCriteriasRestriction($link);
            if ($add) {
                $sql[] = $add;
            }
        }
        return $sql;
    }

    /**
     * Append date and time restriction in an sql request
     * @param link with previous condition
     */
    public function addSqlCriteriasRestriction($link = 'AND')
    {
        $sql = "";
        //Get all criterias sql restriction criterias
        foreach ($this->criterias as $criteria) {
            $add = $criteria->getSqlCriteriasRestriction($link);
            if ($add) {
                $sql .= $add;
                $link = 'AND';
            }
        }
        return $sql;
    }


    /**
     * Build the bookmark URL, which contains all the criteria's values
     * @return string string to be stored by the bookmarking system
     **/
    public function buildBookmarkUrl()
    {
        $bookmark_criterias = '?find=1';
        foreach ($this->criterias as $criteria) {
            $bookmark_criterias .= $criteria->getBookmarkUrl();
        }
        return $_SERVER["REQUEST_URI"] . $bookmark_criterias;
    }


    /**
     * Add a new criteria to the report
     **/
    public function addCriteria($criteria)
    {
        $this->criterias[] = $criteria;
    }


    /**
     * Delete a criteria
     */
    public function delCriteria($name)
    {
        foreach ($this->criterias as $key => $crit) {
            if ($crit->getName() == $name) {
                unset($this->criterias[$key]);
            }
        }
    }


    /**
     * Add a new column in the criterias selection form
     **/
    public function startColumn()
    {
        if ($this->cpt == 0) {
            echo "<tr class='tab_bg_1'>";
        }
        echo "<td>";
        $this->cpt++;
    }


    /**
     * End a column in the criterias selection form
     **/
    public function endColumn()
    {
        echo "</td>";
        if ($this->cpt == 4) {
            echo "</tr>";
            $this->cpt = 0;
        }
    }


    /**
     * Close a column in the criterias selection form
     **/
    public function closeColumn()
    {
        if ($this->cpt > 0) {
            while ($this->cpt < 4) {
                echo "<td></td>";
                $this->cpt++;
            }
            $this->cpt = 0;
            echo "</tr>";
        }
    }

    /**
     * Get the fields used for order
     *
     * @param $default string, name of the column used by default
     *
     * @return array of column names
     */
    public function getOrderByFields($default)
    {
        if (!isset($_REQUEST['sort'])) {
            $_REQUEST['sort'] = $default;
        }
        $colsort = $_REQUEST['sort'];

        foreach ($this->columns as $colname => $column) {
            if ($colname == $colsort) {
                return explode(',', $column->sorton);
            }
        }
        return [];
    }

    /**
     * Build the ORDER BY clause
     *
     * @param $default string, name of the column used by default
     * @apram $setgroupby if true, setGroupBy on same column
     *
     * @return string with SQL clause
     */
    public function getOrderBy($default, $setgroupby = false)
    {
        if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
            $_REQUEST['order'] = 'ASC';
        }
        $order = $_REQUEST['order'];

        $tab = $this->getOrderByFields($default);

        if (count($tab) > 0) {
            if ($setgroupby) {
                $this->setGroupBy($tab);
            }
            return " ORDER BY " . implode(" $order, ", $tab) . " $order";
        }
        return '';
    }

    /**
     * Build the ORDER BY clause
     *
     * @param $default string, name of the column used by default
     * @apram $setgroupby if true, setGroupBy on same column
     *
     * @return []
     */
    public function getNewOrderBy($default, $setgroupby = false)
    {
        if (!isset($_REQUEST['order']) || $_REQUEST['order'] != 'DESC') {
            $_REQUEST['order'] = 'ASC';
        }
        $order = $_REQUEST['order'];

        $tab = $this->getOrderByFields($default);

        if (count($tab) > 0) {
            if ($setgroupby) {
                $this->setNewGroupBy($tab);
            }
            return [implode(" $order, ", $tab) . " $order"];
        }
        return [];
    }
}
