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

namespace GlpiPlugin\Reports;

use AllowDynamicProperties;
use CommonDBTM;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\DBAL\QueryExpression;
use Glpi\Exception\Http\BadRequestHttpException;
use Glpi\Search\Output\HTMLSearchOutput;
use Glpi\Search\SearchEngine;
use Html;
use InvalidArgumentException;
use RuntimeException;
use SavedSearch;
use Search;
use Session;
use Toolbox;

/**
 * Class to create, execute and display a new record
 * The class stores a collection of criterias and
 * manage :
 *   - criterias selection form
 *   - query executing using with criterias restriction
 *   - result display & export (HTML, PDF, CSV, SLK)
 **/
#[AllowDynamicProperties]
class AutoReport extends CommonDBTM
{
    public static $rightname = 'config';

    /**
     * Number of <td> per row of the criteria form: two criteria (label + widget) per row.
     * It is also the width of the grid the legacy startColumn() / endColumn() API fills.
     */
    public const CRITERIA_COLUMNS = 4;

    private $criterias = [];
    private $columns = [];
    private $group_by = [];
    private $columns_mapping = [];
    private $sql = "";
    private $name = "";
    private $subname = "";
    private $cpt = 0;
    private $title = '';
    /**
     * Output type actually requested, validated against the modes the core supports.
     * Read back by footer(), which runs in another scope than execute().
     */
    private $output_type = Search::HTML_OUTPUT;
    /**
     * Whether execute() already emitted the GLPI footer, so footer() does not emit a second one.
     */
    private $footer_displayed = false;
    /**
     * Criteria values resolved for the current request, plus the find, sort and order flags that
     * go with them. This holds what used to be written back into $_POST: the pager and the export
     * form are built from it, so a report reached through GET keeps its criteria without any
     * superglobal being rewritten.
     *
     * @var array
     */
    private $request_parameters = [];
    /**
     * Sort direction the report falls back to when the request carries none.
     *
     * @var string
     */
    private $default_order = 'ASC';


    public function __construct($title = '')
    {
        // The match is made on the path only and the captures are bound to a single segment: the
        // query string used to take part in the match and the greedy groups shifted as soon as a
        // parameter carried "/report/xxx/". A URI that matches nothing left $regs empty, so both
        // assignments raised an "Undefined array key" warning on every load.
        $path = (string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
        if (!preg_match('@/(?:plugins|marketplace)/([^/]+)/report/([^/]+)/@', $path, $regs)) {
            throw new BadRequestHttpException();
        }
        $this->plug = $regs[1];
        $this->name = $regs[2];
        Report::includeLocales($this->name, $this->plug);
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
     * DEPRECATED : should use ColumnMap
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
     *        sql column name => Column object
     **/
    public function setColumns($columns)
    {
        $this->columns = [];
        foreach ($columns as $name => $column) {
            if ($column instanceof Column) {
                $this->columns[$column->name] = $column;
            } else {
                // For compat with setColumnsNames - default text mode
                $this->columns[$name] = new Column($name, $column);
            }
        }
    }


    /**
     * Set the criteria of the request to be executed.
     *
     * A raw SQL string used to be accepted here and handed straight to $DB->doQuery().
     * None of the reports shipped with the plugin took that branch, but the method is
     * public and the third party plugins that declare their own reports under report/
     * inherited an engine that executes arbitrary SQL, which is how an injection gets
     * written. Only query builder criteria are accepted now.
     *
     * @param array $sql criteria, in the $DB->request() format
     **/
    public function setSqlRequest($sql)
    {
        if (!is_array($sql)) {
            throw new InvalidArgumentException(
                'AutoReport::setSqlRequest() expects query builder criteria, not a raw SQL string.',
            );
        }

        $this->sql = $sql;
    }


    /**
     * Set report's name
     * @param $name - the name of the report
     **/
    public function setName($name)
    {
        [$this->plug, $this->name] = explode('.', $name, 2);
    }


    /**
     * Set report's Title
     *
     * @param $title - the title of the report
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
        return isset($this->request_parameters['find']);
    }


    /**
     * @param     $start
     * @param     $numrows
     * @param     $target
     * @param     $parameters
     * @param string|int $item_type_output       itemtype the export is built for (0: no export)
     * @param array|int  $item_type_output_param
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
        TemplateRenderer::getInstance()->display(
            '@reports/autoreport/pager.html.twig',
            self::getPagerData(
                $start,
                $numrows,
                $target,
                $parameters,
                $item_type_output,
                $item_type_output_param,
                $additional_info,
            ),
        );
    }

    /**
     * Same pager as printPager(), returned as a string instead of being displayed.
     *
     * @param     $start
     * @param     $numrows
     * @param     $target
     * @param     $parameters
     * @param string|int $item_type_output       itemtype the export is built for (0: no export)
     * @param array|int  $item_type_output_param
     */
    public static function renderPager(
        $start,
        $numrows,
        $target,
        $parameters,
        $item_type_output = 0,
        $item_type_output_param = 0,
        $additional_info = ''
    ): string {
        return TemplateRenderer::getInstance()->render(
            '@reports/autoreport/pager.html.twig',
            self::getPagerData(
                $start,
                $numrows,
                $target,
                $parameters,
                $item_type_output,
                $item_type_output_param,
                $additional_info,
            ),
        );
    }

    /**
     * Variables of the pager template.
     *
     * Every URL is built here and escaped by the template; the two widgets of the core (list
     * limit form and output format selector) are built with their display option turned off.
     */
    private static function getPagerData(
        $start,
        $numrows,
        $target,
        $parameters,
        $item_type_output,
        $item_type_output_param,
        $additional_info
    ): array {
        $start = (int) $start;
        $numrows = (int) $numrows;
        $list_limit = (int) $_SESSION['glpilist_limit'];
        $target = (string) $target;
        $parameters = (string) $parameters;

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

        if (!str_contains($target, '?')) {
            $fulltarget = $target . "?" . $parameters;
        } else {
            $fulltarget = $target . "&" . $parameters;
        }

        $export = null;
        if (
            !empty($item_type_output)
            && isset($_SESSION["glpiactiveprofile"])
            && (Session::getCurrentInterface() == "central")
            && $numrows > 0
        ) {
            $export_parameters = trim($parameters, '&');
            if (!str_contains($export_parameters, 'start')) {
                $export_parameters .= "&start=$start";
            }

            $hidden = [];
            foreach (explode("&", $export_parameters) as $pair) {
                $pos = Toolbox::strpos($pair, '=');
                if ($pos === false) {
                    continue;
                }
                $field_name = urldecode(Toolbox::substr($pair, 0, $pos));
                // Second barrier against the session token reaching an URL: this form is
                // declared method='GET', so every hidden field written here comes back in
                // the query string of the export request.
                if (str_starts_with($field_name, '_glpi_')) {
                    continue;
                }
                $hidden[] = [
                    'name'  => $field_name,
                    'value' => urldecode(Toolbox::substr($pair, $pos + 1)),
                ];
            }

            $export = [
                'action'          => $_SERVER['REQUEST_URI'],
                'item_type'       => $item_type_output,
                'item_type_param' => is_array($item_type_output_param)
                    ? Toolbox::prepareArrayForInput($item_type_output_param)
                    : null,
                'hidden'          => $hidden,
                'format_dropdown' => Dropdown::showFromArray(
                    'display_type',
                    self::getOutputFormats($item_type_output),
                    ['display' => false],
                ),
            ];
        }

        return [
            'first_href'      => $start != 0 ? $fulltarget . '&start=0' : null,
            'back_href'       => $start != 0 ? $fulltarget . '&start=' . $back : null,
            'next_href'       => $forward < $numrows ? $fulltarget . '&start=' . $forward : null,
            'last_href'       => $forward < $numrows ? $fulltarget . '&start=' . $end : null,
            'pager_form'      => Html::printPagerForm("$fulltarget&start=$start", false),
            // Public API: a report passing a value built from the request must not inject markup,
            // so the template escapes it.
            'additional_info' => (string) $additional_info,
            'export'          => $export,
            'current_start'   => $current_start,
            'current_end'     => $current_end,
            'numrows'         => $numrows,
        ];
    }

    /**
     * Output formats offered by the export selector of the pager: the list of
     * Dropdown::showOutputFormat(), which can only echo its selector.
     *
     * @param mixed $itemtype itemtype the export is built for
     *
     * @return array<string|int, string>
     */
    private static function getOutputFormats($itemtype): array
    {
        $values = [];
        $values[Search::PDF_OUTPUT_LANDSCAPE]       = __('Current page in landscape PDF');
        $values[Search::PDF_OUTPUT_PORTRAIT]        = __('Current page in portrait PDF');
        $values[Search::CSV_OUTPUT]                 = __('Current page in CSV');
        $values[Search::ODS_OUTPUT]                 = __('Current page as Open Document format (.ods)');
        $values[Search::XLSX_OUTPUT]                = __('Current page as Office Open XML (.xlsx)');
        $values['-' . Search::PDF_OUTPUT_LANDSCAPE] = __('All pages in landscape PDF');
        $values['-' . Search::PDF_OUTPUT_PORTRAIT]  = __('All pages in portrait PDF');
        $values['-' . Search::CSV_OUTPUT]           = __('All pages in CSV');
        $values['-' . Search::ODS_OUTPUT]           = __('All pages as Open Document format (.ods)');
        $values['-' . Search::XLSX_OUTPUT]          = __('All pages as Office Open XML (.xlsx)');

        if ($itemtype != "Stat") {
            // Do not show this option for stat page
            $values['-' . Search::NAMES_OUTPUT] = __('Copy names to clipboard');
        }

        return $values;
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
            // SQL injection: list_limit flows unfiltered into $limit and is concatenated raw
            // into the "LIMIT $start,$limit" clause of a doQuery() below. Force an integer.
            $_SESSION['glpilist_limit'] = (int) $_POST['list_limit'];
        }

        $limit = (int) $_SESSION['glpilist_limit'];

        $output_type = Search::HTML_OUTPUT;
        if (isset($_GET["display_type"])) {
            $output_type = self::validateOutputType($_GET["display_type"]);
        }

        // $values never existed in this scope: the variable variables loop that used to stand
        // here, and the display_type override that followed it, could not run. The export
        // links carry display_type in the request, which is read above.
        $start = 0;

        $itemtype = self::class;

        // Keep the resolved type on the instance: footer() runs in a scope of its own and used
        // to read an undefined variable, which the ?? operator silently turned into HTML.
        $this->output_type = $output_type;
        $output = SearchEngine::getOutputForLegacyKey($output_type);
        $is_html_output = $output instanceof HTMLSearchOutput;
        $html_output = '';
        $title = $this->title;
        if ($this->subname) {
            $title = sprintf(__('%1$s - %2$s'), $title, $this->subname);
        }

        $numrows = 0;
        $res = [];
        $paged_by_query = false;
        // setSqlRequest() now refuses anything but criteria, so there is no raw string branch
        // left to run here. The test remains for a report that never called it at all.
        if (is_array($this->sql)) {
            $res = $DB->request($this->sql);
            $numrows = ($res ? count($res) : 0);
        }

        if ($limit) {
            $start = (isset($_GET["start"]) ? intval($_GET["start"]) : 0);
            if ($start >= $numrows) {
                $start = 0;
            }
            if (($start > 0) || (($start + $limit) < $numrows)) {
                if (is_array($this->sql)) {
                    $criteria = $this->sql;
                    // The query builder expects two keys; a concatenated "LIMIT $start,$limit"
                    // turned page 2 of a 20 row page into LIMIT '2020', so the pagination never
                    // moved and the server rendered an arbitrarily large result set.
                    $criteria['START'] = (int) $start;
                    $criteria['LIMIT'] = (int) $limit;
                    $res = $DB->request($criteria);
                    $paged_by_query = true;
                }
            }
        } else {
            $start = 0;
        }

        // Variables of the result template (HTML output only). The export path below never reads
        // them: it keeps building $headers and $rows for SearchEngine as it always did.
        $view = [
            'title'     => $title,
            'empty'     => false,
            'republish' => [],
            'pager'     => '',
            'massive'   => null,
            'headers'   => [],
            'rows'      => [],
        ];

        if ($numrows == 0) {
            if (!$HEADER_LOADED) {
                Html::header($title, '', "utils", "report");
                \Report::title();
            }
            $view['empty'] = true;
            TemplateRenderer::getInstance()->display('@reports/autoreport/results.html.twig', $view);
            $this->footer_displayed = true;
            Html::footer();
        } elseif ($is_html_output) {
            if (!$HEADER_LOADED) {
                Html::header($title, '', "utils", "report");
                \Report::title();
            }

            // The pager links and the hidden fields of the export form republish the request.
            // They used to be built from $_POST alone, which is why the resolved criteria were
            // injected into it; take them from the values resolved for this request instead, and
            // complete them with what was actually posted.
            $republished = $this->request_parameters;
            $pairs       = [];
            foreach ($_POST as $post_key => $post_val) {
                if (!array_key_exists($post_key, $republished)) {
                    $republished[$post_key] = $post_val;
                }
            }
            foreach ($republished as $key => $val) {
                // The criteria form is closed with a hidden _glpi_csrf_token: the token was
                // therefore part of $_POST and ended up concatenated into the pagination string,
                // which printPager() publishes in every href and re-splits into the hidden fields
                // of a method='GET' export form. A session token valid until consumption was thus
                // written to the browser history, the proxy access logs and the Referer header.
                // Internal _glpi_* fields have no business in a report URL, and every generated
                // form gets a fresh token of its own anyway.
                // list_limit is consumed above, where it becomes the session preference. It used
                // to be unset from $_POST so it would not be republished here; the superglobal is
                // now left alone and the exclusion is expressed where it belongs.
                if (str_starts_with((string) $key, '_glpi_') || $key === 'list_limit') {
                    continue;
                }
                // Arrays are expanded recursively into name[key] fields, as Html::hidden() did
                $view['republish'] = array_merge($view['republish'], self::flattenHiddenFields((string) $key, $val));
                $pairs[$key] = $val;
            }
            // http_build_query() handles nested values at any depth: urlencode() raised a
            // TypeError (500) as soon as a posted parameter was nested more than one level
            $param = http_build_query($pairs, '', '&');
            $view['pager'] = self::renderPager($start, $numrows, $_SERVER['REQUEST_URI'], $param, "GlpiPlugin\Reports\AutoReport");
        }

        if ($res && ($numrows > 0)) {
            if (!isset($_GET["display_type"]) || $is_html_output) {
                if (isset($options['withmassiveaction']) && class_exists($options['withmassiveaction'])) {
                    $massformid = 'massform' . $options['withmassiveaction'];
                    $view['massive'] = [
                        'form_id' => $massformid,
                        'actions' => Html::showMassiveActions(['container' => $massformid, 'display' => false]),
                    ];
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
            $colsname = [];
            // if $columns is not empty, display $columns
            if (count($this->columns) > 0) {
                foreach ($this->columns as $colname => $column) {
                    // display only $columns that are valid
                    if (in_array($colname, $sqlcols)) {
                        if ($is_html_output) {
                            $view['headers'][] = (string) $column->showHtmlTitle($output, $header_num);
                        } else {
                            $headers[] = $column->showExportTitle($output, $header_num);
                        }
                        $colsname[$colname] = $column;
                    }
                }
            } else { // else display default columns from SQL query
                foreach ($sqlcols as $colname) {
                    $column = new Column($colname, $colname);
                    if ($is_html_output) {
                        $view['headers'][] = (string) $column->showHtmlTitle($output, $header_num);
                    } else {
                        $headers[] = $column->showExportTitle();
                    }
                    $colsname[$colname] = $column;
                }
            }

            $list = [];
            $i = 0;

            foreach ($res as $k => $data) {
                $list[$i] = $data;
                $i++;
            }

            $row_num = 0;
            if (!empty($sqlvalues)) {
                // When the page was fetched with START / LIMIT, $list holds the rows of that page
                // only, indexed from 0: walking it from $start rendered nothing at all on every
                // page after the first (an empty table, or an empty export of the current page).
                $first = $paged_by_query ? 0 : $start;
                for ($i = $first; ($i < $numrows) && ($i < $end_display); $i++) {
                    $row_num++;
                    $current_row = [];

                    $colnum = 0;

                    $html_cells = [];
                    $num = 1;
                    foreach ($colsname as $colname => $column) {
                        if ($is_html_output) {
                            $html_cells[] = $output::showItem($column->showValue($output_type, $list[$i]), $num, $row_num);
                        } else {
                            $current_row[$itemtype . '_' . (++$colnum)] = ['displayname' => $column->showValue($output_type, $list[$i])];
                        }
                    }

                    $rows[$row_num] = $current_row;
                    if ($is_html_output) {
                        $view['rows'][] = ['odd' => $i % 2 === 1, 'cells' => $html_cells];
                    }
                }

                // Total line, once after the data. It used to be emitted inside the loop above,
                // after every single row, which printed one running subtotal per row instead of
                // the one total line the option was introduced for.
                if ($is_html_output && !empty($options['withtotal'])) {
                    $html_cells = [];
                    $num = 1;
                    foreach ($colsname as $colname => $column) {
                        $html_cells[] = $output::showItem(
                            $column->showNewTotal($output_type, $num, $row_num),
                            $num,
                            $row_num,
                        );
                    }
                    $view['rows'][] = ['odd' => false, 'cells' => $html_cells];
                }
            }

            if ($is_html_output) {
                TemplateRenderer::getInstance()->display('@reports/autoreport/results.html.twig', $view);
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
        if ($is_html_output) {
            $this->footer_displayed = true;
            Html::footer();
        }
    }

    /**
     * Display an alert in the page of a report (a missing criteria, a perimeter the session
     * cannot read...). The text is plain text, escaped by the template.
     *
     * @param string $text message
     * @param string $type bootstrap alert type: danger, warning, info, success
     */
    public static function displayMessage(string $text, string $type = 'danger'): void
    {
        TemplateRenderer::getInstance()->display('@reports/report/message.html.twig', [
            'type' => in_array($type, ['danger', 'warning', 'info', 'success'], true) ? $type : 'danger',
            'text' => $text,
        ]);
    }

    /**
     * Expand a value into hidden fields the way Html::hidden() does: an array becomes one
     * name[key] field per leaf.
     *
     * @param string $name  field name
     * @param mixed  $value field value
     *
     * @return array<int, array{name: string, value: string}>
     */
    private static function flattenHiddenFields(string $name, $value): array
    {
        if (!is_array($value)) {
            return [['name' => $name, 'value' => is_scalar($value) ? (string) $value : '']];
        }

        $fields = [];
        foreach ($value as $key => $item) {
            $fields = array_merge($fields, self::flattenHiddenFields($name . '[' . $key . ']', $item));
        }

        return $fields;
    }

    /**
     * Confront a requested display type with the output modes the core actually supports.
     *
     * SearchEngine::getOutputForLegacyKey(int $output_type) raises a TypeError on a non numeric
     * value and a RuntimeException on a key outside the enumeration. The plugin declares no
     * strict_types and catches neither, so an arbitrary display_type answered 500 with a full
     * stack trace instead of a 400, giving a trivial way to generate server errors at will.
     *
     * @param mixed $output_type
     */
    private static function validateOutputType($output_type): int
    {
        // Search::GLOBAL_SEARCH (-1) is deliberately absent: it belongs to the global search of
        // the core and no report of this plugin has any use for it. It was accepted here while
        // the escaping of the columns keyed on the value Search::HTML_OUTPUT (0), and the core
        // maps -1 onto an HTMLSearchOutput all the same -- so display_type=-1 rendered every
        // cell of every AutoReport into a <td> unescaped. isHtmlOutputType() now closes that
        // divergence at the sink; withdrawing the format closes it at the entry as well.
        $allowed_output_types = [
            Search::HTML_OUTPUT,
            Search::PDF_OUTPUT_LANDSCAPE,
            Search::PDF_OUTPUT_PORTRAIT,
            Search::CSV_OUTPUT,
            Search::ODS_OUTPUT,
            Search::XLSX_OUTPUT,
            Search::NAMES_OUTPUT,
        ];

        if (!is_numeric($output_type) || !in_array((int) $output_type, $allowed_output_types, true)) {
            throw new BadRequestHttpException();
        }

        return (int) $output_type;
    }

    /**
     * Whether a legacy display type is rendered as HTML, and so whether the values that go into
     * it have to be escaped.
     *
     * The columns used to decide that by comparing the display type with the value
     * Search::HTML_OUTPUT (0), while the core picks its renderer from a class:
     * SearchEngine::getOutputForLegacyKey() answers an HTMLSearchOutput for HTML_OUTPUT and for
     * GLOBAL_SEARCH (-1) alike. Every cell of a report opened with display_type=-1 therefore
     * reached HTMLSearchOutput::showItem(), which writes it into a <td> as it stands -- a stored
     * XSS on every report of this engine. Ask the core which renderer it would build rather than
     * reading the number, so a format added later and rendered as HTML is covered here for free.
     *
     * A display type the core does not know cannot be rendered at all, so answer true: an
     * unexpected value is escaped rather than let through.
     *
     * @param mixed $output_type
     */
    public static function isHtmlOutputType($output_type): bool
    {
        if (!is_numeric($output_type)) {
            return true;
        }

        try {
            return SearchEngine::getOutputForLegacyKey((int) $output_type) instanceof HTMLSearchOutput;
        } catch (RuntimeException $e) {
            return true;
        }
    }

    public function footer()
    {
        // $output_type was never assigned in this scope: the ?? operator only hid the undefined
        // variable notice and always yielded HTML_OUTPUT. Html::footer() was therefore appended
        // whatever the requested display type, corrupting every CSV, ODS and XLSX export, and
        // duplicating the footer in HTML since execute() had already emitted it.
        if ($this->footer_displayed) {
            return;
        }

        $output = SearchEngine::getOutputForLegacyKey($this->output_type);
        if ($output instanceof HTMLSearchOutput) {
            $this->footer_displayed = true;
            Html::footer();
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
        if (isset($_REQUEST["display_type"])
            && self::validateOutputType($_REQUEST["display_type"]) !== Search::HTML_OUTPUT) {
            return;
        }
        if (!$HEADER_LOADED) {
            $title = $this->title;
            if ($this->subname) {
                $title = sprintf(__('%1$s - %2$s'), $title, $this->subname);
            }

            if (isStat($this->name)) {
                Html::header($title, '', "helpdesk", "stat");
                \Stat::title();
            } else {
                Html::header(
                    $title,
                    '',
                    "tools",
                    "report",
                );
                \Report::title();
            }
        }

        $field = 'plugin_reports_' . $this->name;
        if ($this->plug != 'reports') {
            $field = 'plugin_reports_' . $this->plug . "_" . $this->name;
        }
        Session::checkRight($field, READ);

        //Display form only if there're criterias
        if (!empty($this->criterias)) {
            TemplateRenderer::getInstance()->display('@reports/autoreport/criteria_form.html.twig', [
                'action'     => $_SERVER['REQUEST_URI'],
                'title'      => __('Search criteria', 'reports'),
                'nb_columns' => self::CRITERIA_COLUMNS,
                'rows'       => $this->getCriteriaRows(),
            ]);
        }
    }

    /**
     * Rows of the criteria form.
     *
     * The cells declared by AutoCriteria::getCriteriaFields() are packed two criteria cells
     * (label + widget) per row. A criteria that still renders itself through the legacy
     * displayCriteria() API -- the criteria of other plugins written before getCriteriaFields()
     * existed -- is rendered as it always was, through startColumn() / endColumn(), and its
     * markup is handed to the template as complete rows.
     *
     * @return array<int, array{cells?: array<int, array<string, string>>, legacy_html?: string}>
     */
    private function getCriteriaRows(): array
    {
        $rows        = [];
        $cells       = [];
        $legacy_html = null;
        $per_row     = intdiv(self::CRITERIA_COLUMNS, 2);

        foreach ($this->criterias as $criteria) {
            if ($criteria->usesLegacyDisplay()) {
                if ($cells !== []) {
                    $rows[] = ['cells' => $cells];
                    $cells  = [];
                }
                // The legacy API echoes its cells (startColumn() / endColumn() and whatever the
                // criteria prints in between): there is no other way to collect them than to
                // buffer the output. Consecutive legacy criteria share their rows, as before.
                ob_start();
                $criteria->displayCriteria();
                $legacy_html = ($legacy_html ?? '') . ob_get_clean();
                continue;
            }

            if ($legacy_html !== null) {
                $rows[]      = ['legacy_html' => $legacy_html . $this->closeLegacyRow()];
                $legacy_html = null;
            }

            foreach ((array) $criteria->getCriteriaFields() as $cell) {
                $cells[] = $cell;
                if (count($cells) === $per_row) {
                    $rows[] = ['cells' => $cells];
                    $cells  = [];
                }
            }
        }

        if ($legacy_html !== null) {
            $rows[] = ['legacy_html' => $legacy_html . $this->closeLegacyRow()];
        }
        if ($cells !== []) {
            $rows[] = ['cells' => $cells];
        }

        return $rows;
    }

    /**
     * Markup closeColumn() emits to complete the row left open by the legacy API.
     */
    private function closeLegacyRow(): string
    {
        ob_start();
        $this->closeColumn();

        return (string) ob_get_clean();
    }


    public function manageCriteriasValues()
    {
        // Collect here what the request resolved to, instead of injecting it into $_POST: the
        // superglobals stay read-only for the whole request, and the pager and the export form
        // are built from this array by displayReport().
        $this->request_parameters = [];
        foreach ($this->criterias as $criteria) {
            $criteria->manageCriteriaValues();
            foreach ($criteria->getParameters() as $parameter => $value) {
                $this->request_parameters[$parameter] = $value;
            }
        }

        //If selectio form is validated, then stores it
        if (isset($_GET['find']) || isset($_POST['find'])) {
            $this->request_parameters['find'] = true;
        }
        // Order by
        // Sort and order are single values: a nested one (sort[]=x) is dropped
        foreach (['sort', 'order'] as $key) {
            $value = $_GET[$key] ?? $_POST[$key] ?? null;
            if (is_scalar($value)) {
                $this->request_parameters[$key] = $value;
            }
        }
    }

    /**
     * Criteria values resolved for the current request.
     *
     * @return array
     **/
    public function getRequestParameters()
    {
        return $this->request_parameters;
    }

    /**
     * Value a criteria resolved to for the current request.
     *
     * @param string $name    name of the criteria
     * @param mixed  $default value returned when the request carries none
     **/
    public function getRequestParameter(string $name, $default = null)
    {
        return $this->request_parameters[$name] ?? $default;
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
        if ($sql === []) {
            // Every report appends this value as one more WHERE entry ($criteria['WHERE'][] =
            // ...), and the query builder turns an empty array into "AND ()": a SQL syntax error,
            // that is a 500, as soon as the form was submitted with every criteria left blank.
            // Answer a neutral condition instead.
            return [new QueryExpression('true')];
        }
        return $sql;
    }

    /**
     * Append date and time restriction in an sql request
     * @param link with previous condition
     *
     * @deprecated Kept for third-party reports that still concatenate SQL. Prefer
     *             getNewSqlCriteriasRestriction(): its array criteria are quoted by
     *             $DB->request() itself.
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
     * Add a new column in the criterias selection form.
     *
     * Legacy API, kept for the criteria that render themselves through displayCriteria() (those
     * of other plugins in particular): it echoes the opening of a cell, which the criteria fills
     * by echoing in turn. displayCriteriasForm() collects that output and places it in the form
     * template. New criteria implement AutoCriteria::getCriteriaFields() instead.
     **/
    public function startColumn()
    {
        TemplateRenderer::getInstance()->display('@reports/autoreport/legacy_column.html.twig', [
            'open_row'  => $this->cpt == 0,
            'open_cell' => true,
        ]);
        $this->cpt++;
    }


    /**
     * End a column in the criterias selection form (legacy API, see startColumn()).
     **/
    public function endColumn()
    {
        $close_row = $this->cpt == self::CRITERIA_COLUMNS;
        TemplateRenderer::getInstance()->display('@reports/autoreport/legacy_column.html.twig', [
            'close_cell' => true,
            'close_row'  => $close_row,
        ]);
        if ($close_row) {
            $this->cpt = 0;
        }
    }


    /**
     * Close a column in the criterias selection form (legacy API, see startColumn()).
     **/
    public function closeColumn()
    {
        if ($this->cpt > 0) {
            TemplateRenderer::getInstance()->display('@reports/autoreport/legacy_column.html.twig', [
                'empty_cells' => max(0, self::CRITERIA_COLUMNS - $this->cpt),
                'close_row'   => true,
            ]);
            $this->cpt = 0;
        }
    }

    /**
     * Set the sort direction the report falls back to when the request carries none.
     *
     * Reports used to write their default straight into $_REQUEST, which made every later read
     * of the sort direction return a value the plugin had produced rather than the submitted one.
     *
     * @param string $order ASC or DESC
     **/
    public function setDefaultOrder($order)
    {
        $this->default_order = $order === 'DESC' ? 'DESC' : 'ASC';
    }

    /**
     * Sort direction requested for this report: the default of the report unless the request
     * explicitly asked for another one.
     *
     * @return string
     **/
    private function getRequestedOrder()
    {
        $order = $this->request_parameters['order'] ?? $_REQUEST['order'] ?? $this->default_order;

        return $order === 'DESC' ? 'DESC' : 'ASC';
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
        // The default used to be written into $_REQUEST, which turned every later read of the
        // sort column into a read of a value the plugin had produced. Resolve it locally, so
        // what the request actually carries stays what the rest of the code reads.
        $colsort = $this->request_parameters['sort'] ?? $_REQUEST['sort'] ?? $default;

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
        $order = $this->getRequestedOrder();

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
        $order = $this->getRequestedOrder();

        $tab = $this->getOrderByFields($default);
        $tab = array_filter($tab);

        if (count($tab) > 0) {
            if ($setgroupby) {
                $this->setGroupBy($tab);
            }
            return ["ORDERBY" => implode(" $order, ", $tab) . " " . $order];
        }
        return [];
    }
}
