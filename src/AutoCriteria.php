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

use CommonDBTM;
use Glpi\Application\View\TemplateRenderer;
use ReflectionMethod;
use Session;

/**
 * AutCriteria class manage a new search & filtering criteria
 * It manage display & sql code associated
 */
abstract class AutoCriteria
{
    //Criteria's internal name
    protected $name = "";

    //Label of the criteria (refers to an entry in the locale file)
    private $criterias_labels = [];

    //Parameters are stored as name => value
    private $parameters = [];

    //Field in the SQL request (can be table.field)
    private $sql_field = "";

    //Report in which the criteria will be added to
    private $report = null;


    /**
     * Contructor
     * @param $report              - the report in which the criteria is added
     * @param $name               - the criteria's name
     * @param $sql_field          - the sql field associated with the criteria
     *                            (can be set later with setSqlField).(default '')
     *          - Sql_field can be prefixed with table name
     *          - if sql_field=='' then sql_field=name
     * @param $label     string   (default NULL)
    **/
    public function __construct($report, $name, $sql_field = '', $label = null)
    {

        $this->setName($name);
        if ($sql_field) {
            $this->setSqlField($sql_field);
        } else {
            $this->setSqlField($name);
        }
        if (!is_null($label)) {
            $this->addCriteriaLabel($this->getName(), $label);
        }
        $this->setReport($report);
        $this->report->addCriteria($this);
        $this->setDefaultValues();
    }


    //-------------- Getters ------------------//

    /**
     * Get report object
    **/
    public function getReport()
    {
        return $this->report;
    }


    /**
     * Get all parameters associated with the criteria
    **/
    public function getParameterValue()
    {
        return $this->parameters[$this->name];
    }


    /**
     * Same value, reduced to a string.
     *
     * manageCriteriaValues() copies the criteria out of $_GET/$_POST as they came, so a
     * criteria named "name" submitted as name[]=x arrives here as an array. Every consumer of
     * a text value -- htmlescape() when the form is redrawn, Search::makeTextCriteria() when
     * the restriction is built -- is typed against a string and raised an uncaught TypeError
     * on that array: a 500 in place of the report, and the full server paths in the trace on
     * an instance left with display_errors on. IntegerCriteria already defended itself with a
     * cast of its own; putting the coercion here lets every criteria inherit it rather than
     * repeat the guard class by class.
     *
     * A non-scalar answers the empty string, which every caller already reads as "criteria
     * left blank" -- the report is rendered whole rather than half built on a value nobody
     * could have typed.
     **/
    public function getScalarParameterValue(): string
    {
        $value = $this->getParameterValue();

        return is_scalar($value) ? (string) $value : '';
    }


    /**
     * Get sql_field associated with the criteria
     *
     * @return - the sql_field associated with the criteria
    **/
    public function getSqlField()
    {
        return $this->sql_field;
    }


    /**
     * Get a specific parameter
     *
     * @param $parameter - the parameter's name
     *
     * @return - the parameter's value
    **/
    public function getParameter($parameter)
    {
        return $this->parameters[$parameter];
    }


    /**
     * Get the label associated with the criteria
     *
     * @param $parameter - the parameter's name
     *
     * @return - label associated with the criteria
    **/
    public function getCriteriaLabel($parameter = '')
    {
        // A criteria built with a null label has no entry here: answer an empty label rather
        // than raising an "Undefined array key" warning in the middle of the form.
        return $this->criterias_labels[$parameter ? $parameter : $this->getName()] ?? '';
    }


    /**
     * Get the criteria's title
    **/
    public function getSubName()
    {
        return "";
    }


    /**
     * Get criteria's name
     *
     * @return criteria's name
    **/
    public function getName()
    {
        return $this->name;
    }



    /**
     * Get all the parameters associated with the criteria
     *
     * @return - the parameters
    **/
    public function getParameters()
    {
        return $this->parameters;
    }


    /**
     * Build Sql code associated with the criteria (to be included into the global report's sql query)
     *
     * @param $link   - default 'AND')
     *
     * @return string the where sql request ('' when no criteria applies)
     *
     * @deprecated Kept for third-party reports that still concatenate SQL. Prefer
     *             getNewSqlCriteriasRestriction(): its array criteria are quoted by
     *             $DB->request() itself.
    **/
    public function getSqlCriteriasRestriction($link = 'AND')
    {
        global $DB;
        // Force a scalar context before $DB::quoteValue(): a value posted as an array
        // (param[]=x) would otherwise reach the helper — which expects a string — and
        // raise a PHP error instead of failing cleanly. Legitimate scalar values are
        // unaffected (the result is quoted as a string literal either way).
        return $link . " " . $DB::quoteName($this->getSqlField()) . "=" . $DB::quoteValue((string) $this->getParameterValue()) . " ";
    }

    /**
     * Build Sql code associated with the criteria (to be included into the global report's sql query)
     *
     * @param $link   - default 'AND')
     *
     * @return - sql request
     **/
    public function getNewSqlCriteriasRestriction($link = 'AND')
    {
        return [$this->getSqlField() => $this->parameters[$this->getName()]];
    }


    /**
     * Get URL to be used by bookmarking system
     *
     * @return - the bookmark's url associated with the criteria
    **/
    public function getBookmarkUrl()
    {

        // Raw concatenation let a criteria value carrying &, = or / take part in the parsing of
        // the bookmarked URL, up to shifting the report name AutoReport::__construct() reads back
        // from the path. Array values are flattened with their index instead of being stringified.
        $url = "";
        foreach ($this->parameters as $parameter => $value) {
            if (is_array($value)) {
                foreach ($value as $key => $item) {
                    $url .= '&'
                    . urlencode($parameter . '[' . $key . ']') . '=' . urlencode((string) $item);
                }
            } else {
                $url .= '&'
                . urlencode((string) $parameter) . '=' . urlencode((string) $value);
            }
        }
        return $url;
    }


    //-------------- Setters ------------------//

    /**
     * Set report
     *
     * @param $report - the report in which the criteria is put
    **/
    public function setReport($report)
    {
        $this->report = $report;
    }


    /**
     * Set criteria's parameters
     *
     * @param $parameters -  the parameters
    **/
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;
    }


    /**
     * Add a new parameter to the criteria
     * If parameter exists, it overwrites the existing values
     *
     * @param $name   parameter's name
     * @param $value  parameter's value
    **/
    public function addParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }


    /**
     * Set sql field associated with the criteria
     *
     * @param $sql_field - sql field associated with the criteria
    **/
    public function setSqlField($sql_field)
    {
        $this->sql_field = $sql_field;
    }


    /**
     * Set criteria's name
     *
     * @param $name   criteria's name
    **/
    public function setName($name)
    {
        $this->name = strtr($name, '`.', '__');
    }


    /**
     * Add a label to the criteria
     *
     * @param $name   criteria's name
     * @param $label  - add criteria's label
    **/
    public function addCriteriaLabel($name, $label)
    {
        $this->criterias_labels[$name] = $label;
    }


    /**
     * Set criteria's default value()
     * This method is abstract ! Needs to be implemented in each criteria
    **/
    abstract public function setDefaultValues();


    //-------------- Other ------------------//

    /**
     * Cells of the criteria in the selection form.
     *
     * This is the method a criteria implements to appear in the form: the report engine renders
     * the cells through a shared template, so a criteria writes no HTML of its own. Each cell is
     * an array with the keys:
     *  - name:   (string) name of the input, used to build the id of the label
     *  - label:  (string) plain text label, escaped by the template
     *  - field:  (string) HTML of a GLPI core widget, built with 'display' => false
     *            (Dropdown::show(), Html::showDateField(), Html::input()...)
     *  - suffix: (string, optional) plain text shown after the widget (a unit for instance)
     *  - message: (string, optional) plain text warning shown in place of / above the widget
     *
     * The default answer, null, means the criteria still renders itself through the legacy
     * displayCriteria() / startColumn() / endColumn() API: the engine then keeps that path for
     * this criteria, so the criteria of other plugins written that way keep working unchanged.
     *
     * @return array<int, array{name?: string, label?: string, field?: string, suffix?: string, message?: string}>|null
    **/
    public function getCriteriaFields()
    {
        return null;
    }


    /**
     * Tell whether the engine has to render this criteria through the legacy displayCriteria()
     * API rather than through getCriteriaFields().
     *
     * That is the case when getCriteriaFields() is not implemented at all, and also when a
     * subclass overrides displayCriteria() (or displayDropdownCriteria() for the dropdown
     * criteria) below the class that implements getCriteriaFields(): such a subclass customised
     * the legacy rendering, and its customisation must win over the inherited cells.
    **/
    public function usesLegacyDisplay(): bool
    {
        $fields_class = (new ReflectionMethod($this, 'getCriteriaFields'))->getDeclaringClass()->getName();
        if ($fields_class === self::class) {
            return true;
        }

        return $this->isOverriddenBelowFields('displayCriteria')
            || $this->isOverriddenBelowFields('displayDropdownCriteria');
    }


    /**
     * Tell whether a legacy rendering method is overridden in a class that derives from the one
     * declaring getCriteriaFields(), i.e. whether a subclass customised the legacy rendering
     * after the cells were defined.
     *
     * @param string $method name of the legacy rendering method
    **/
    protected function isOverriddenBelowFields(string $method): bool
    {
        if (!method_exists($this, $method)) {
            return false;
        }

        $fields_class    = (new ReflectionMethod($this, 'getCriteriaFields'))->getDeclaringClass()->getName();
        $declaring_class = (new ReflectionMethod($this, $method))->getDeclaringClass()->getName();

        return is_subclass_of($declaring_class, $fields_class);
    }


    /**
     * Display criteria in the criteria's selection form (legacy API).
     *
     * The report engine no longer calls this method for a criteria that implements
     * getCriteriaFields(); it is kept, and still renders the same cells, for the code that calls
     * it directly and for the subclasses of other plugins that override it. A criteria that
     * overrides it is rendered through it, inside the cells opened by startColumn().
    **/
    public function displayCriteria()
    {
        $this->displayCriteriaFields();
    }


    /**
     * Render the cells of getCriteriaFields() through the legacy startColumn() / endColumn()
     * grid, for the callers of displayCriteria().
    **/
    protected function displayCriteriaFields()
    {
        $report = $this->getReport();
        foreach ((array) $this->getCriteriaFields() as $cell) {
            $report->startColumn();
            TemplateRenderer::getInstance()->display('@reports/autoreport/criteria_cell.html.twig', [
                'label' => $cell['label'] ?? '',
            ]);
            $report->endColumn();

            $report->startColumn();
            TemplateRenderer::getInstance()->display('@reports/autoreport/criteria_cell.html.twig', [
                'field'   => $cell['field'] ?? '',
                'suffix'  => $cell['suffix'] ?? '',
                'message' => $cell['message'] ?? '',
            ]);
            $report->endColumn();
        }
    }


    /**
     * Set parameter's values get the criteria working
    **/
    public function manageCriteriaValues()
    {

        foreach ($this->parameters as $parameter => $value) {
            // The resolved value used to be written back into $_POST, so that the pager and the
            // export form, both built from the whole $_POST, carried the criteria of a report
            // reached through GET. Rewriting a superglobal makes every later read of $_POST
            // return a value the plugin produced rather than the one the user submitted, which
            // is the very pattern equipmentbygroups stopped using. Keep the resolved value here;
            // AutoReport publishes it through getRequestParameters().
            if (isset($_GET[$parameter])) {
                $this->parameters[$parameter] = $_GET[$parameter];
            } elseif (isset($_POST[$parameter])) {
                $this->parameters[$parameter] = $_POST[$parameter];
            }
        }
    }

    /**
     * Tell whether the table backing a criteria carries an entity perimeter its posted
     * identifiers can be confronted with. A table with no resolvable itemtype, or one shared
     * by every entity, has none: its identifiers are kept as they were posted.
     *
     * @param mixed $itemtype itemtype resolved from the criteria table
     **/
    protected function hasEntityPerimeter($itemtype): bool
    {
        if (!is_string($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
            return false;
        }

        $item = new $itemtype();

        return $item->isEntityAssign();
    }

    /**
     * Tell whether an identifier posted for a criteria designates a row the current session is
     * allowed to read, so that it may be handed to the query or echoed back as a label.
     *
     * Fails closed: a table with no resolvable itemtype, or a row that no longer exists,
     * yields no match rather than an unchecked one.
     *
     * @param mixed $itemtype itemtype resolved from the criteria table
     * @param mixed $value    identifier posted for the criteria
     **/
    protected function isIdentifierReadable($itemtype, $value): bool
    {
        if (!is_string($itemtype) || !is_a($itemtype, CommonDBTM::class, true)) {
            return false;
        }

        // An array cast to int is 1 in PHP 8, with a warning and nothing else: the perimeter
        // below was then confronted on row 1 of the dropdown table rather than on anything the
        // caller actually posted, so a criteria submitted as name[]=42&name[]=43 walked past
        // the check as soon as row 1 happened to be readable. Refuse the whole shape here, at
        // the base of every criteria class, rather than trust each of them to normalise first.
        if (!is_scalar($value)) {
            return false;
        }

        $item = new $itemtype();
        if (!$item->getFromDB((int) $value)) {
            return false;
        }

        if (!$item->isEntityAssign()) {
            // Table shared by every entity: there is no perimeter to confront.
            return true;
        }

        return Session::haveAccessToEntity(
            $item->fields['entities_id'],
            (bool) ($item->fields['is_recursive'] ?? false),
        );
    }
}
