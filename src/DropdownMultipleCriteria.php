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

use DbUtils;
use Dropdown;

/**
 * Manage criterias from dropdown tables
 */
class DropdownMultipleCriteria extends AutoCriteria
{
    // TODO review this to use itemtype class as primary option
    //Drodown table
    private $table = "";
    //Should display dropdown's childrens value
    private $childrens = false;
    ///Use entity restriction in the dropdown ? (default is current entity)
    private $entity_restrict = -1;
    //Display dropdown comments
    private $displayComments = false;
    // search for zero if true, else treat zero as "all" (no criteria)
    private $searchzero = false;
    // For special condition
    private $condition = [];
    // options for the dropdown multiple
    private $options = '';
    // options for the dropdown multiple
    private $order = [];

    /**
     * @param $report
     * @param $name
     * @param $tableortype  (default '')
     * @param $label        (default '')
     * @param $condition    (default '')
     * @param $options      (default '')
     * @param $order        (default '')
     * */
    public function __construct($report, $name, $tableortype = '', $label = '', $condition = [], $options = '', $order = [])
    {

        parent::__construct($report, $name, $name, $label);

        $this->condition = $condition;

        if (!empty($options)) {
            $this->options = $options;
        }

        if (!empty($order)) {
            $this->order = $order;
        }
        $dbu = new DbUtils();
        if (empty($tableortype)) {
            $this->table = $dbu->getTableNameForForeignKeyField($name);
        } elseif (preg_match("/^glpi_/", $tableortype)) {
            $this->table = $tableortype;
        } elseif ($tableortype == NOT_AVAILABLE) {
            $this->table = NOT_AVAILABLE;
        } else {
            $this->table = $dbu->getTableForItemType($tableortype);
        }
    }

    /**
     * Get criteria's related table
     * */
    public function getTable()
    {
        return $this->table;
    }

    /**
     * Get criteria's related table
     * */
    public function getItemType()
    {
        $dbu = new DbUtils();
        return $dbu->getItemTypeForTable($this->table);
    }

    /**
     * Will display dropdown childrens (in table in hierarchical)
     * */
    public function setWithChildrens()
    {

        //if (in_array($this->getTable(), $CFG_GLPI["dropdowntree_tables"])) {
        // TODO find a solution to check is children exists
        $this->childrens = true;
        //}
    }

    /**
     * Will display dropdown childrens (in table in hierarchical)
     * */
    public function setSearchZero()
    {
        $this->searchzero = true;
    }

    /**
     * Set default criteria value to 0 and entity restriction to current entity only
     * */
    public function setDefaultValues()
    {

        $this->addParameter($this->getName(), 0);
        $this->setEntityRestriction($_SESSION["glpiactive_entity"]);
        $this->setDisplayComments();
    }

    /**
     * Show dropdown comments (enable by defaults)
     * */
    public function setDisplayComments()
    {
        $this->displayComments = true;
    }

    /**
     * Hide dropdown comments
     * */
    public function setNoDisplayComments()
    {
        $this->displayComments = false;
    }

    /**
     * Get display comments status
     * */
    public function getDisplayComments()
    {
        return $this->displayComments;
    }

    /**
     * Change criteria's label
     *
     * @param $label   - the new label to display
     * @param $name    - the name of the criteria whose label should be changed
     *                (if no name is provided, the default criteria will be used)
     *                (default '')
     * */
    public function setCriteriaLabel($label, $name = '')
    {

        if ($name == '') {
            $this->criterias_labels[$this->name] = $label;
        } else {
            $this->criterias_labels[$name] = $label;
        }
    }

    /**
     * Change entity restriction
     *
     * @param $restriction
     * Values are :
     * REPORTS_NO_ENTITY_RESTRICTION : no entity restriction (everything is displayed)
     * REPORTS_CURRENT_ENTITY : only values from the current entity
     * REPORTS_SUB_ENTITIES : values from the current entity + sub-entities
     * */
    public function setEntityRestriction($restriction)
    {
        global $CFG_GLPI;

        switch ($restriction) {
            case REPORTS_NO_ENTITY_RESTRICTION:
                $this->entity_restrict = -1;
                break;

            case REPORTS_CURRENT_ENTITY:
                $this->entity_restrict = $_SESSION["glpiactive_entity"];
                break;

            case REPORTS_SUB_ENTITIES:
                $dbu = new DbUtils();
                $this->entity_restrict = $dbu->getSonsOf('glpi_entities', $_SESSION["glpiactive_entity"]);
                break;
        }
    }

    /**
     * Get entity restrict status
     * */
    public function getEntityRestrict()
    {
        return $this->entity_restrict;
    }

    /**
     * Get list of items
     */
    public function getItems()
    {
        $dbu       = new DbUtils();
        $itemtype  = $dbu->getItemTypeForTable($this->table);
        $item      = $dbu->getItemForItemtype($itemtype);
        $items     = $item->find($this->condition, $this->order);
        $listItems = [];
        foreach ($items as $id => $item) {
            $listItems[$id] = $item['name'];
        }
        return $listItems;
    }

    /**
     * Get criteria's subtitle
     * */
    public function getSubName()
    {

        // Only label the identifiers the session may actually read: the subtitle used to echo
        // back the name of whatever row was posted, which turned the criteria into an oracle
        // on the dropdown tables of the other entities. getDropdownName() was also handed the
        // whole array on a multi-select, where it renders nothing usable.
        $values = $this->getReadableParameterValues();
        if ($values !== []) {
            $names = [];
            foreach ($values as $one) {
                $names[] = Dropdown::getDropdownName($this->getTable(), $one);
            }
            return $this->getCriteriaLabel() . " : " . implode(', ', $names);
        }

        if ($this->searchzero) {
            // zero
            return sprintf(__('%1$s: %2$s'), $this->getCriteriaLabel(), __('None'));
        }

        // All
        return '';
    }

    /**
     * Get URL to be used by bookmarking system
     *
     * @return - the bookmark's url associated with the criteria
    **/
    public function getBookmarkUrl()
    {

        $url = "";
        $parameters = [];
        $values = [];
        if (!empty($this->getParameterValue())) {
            $values = $this->getParameterValue();
        }
        $parameters[$this->getName()] = $values;
        foreach ($parameters as $parameter => $value) {
            $url .= '&' .
            $parameter . '=' . implode(',', $value);
        }
        return $url;
    }

    /**
     * Display criteria in the criteria's selection form
     * */
    public function displayCriteria()
    {

        $this->getReport()->startColumn();
        echo $this->getCriteriaLabel() . '&nbsp;:';
        $this->getReport()->endColumn();

        $this->getReport()->startColumn();
        $this->displayDropdownCriteria();
        $this->getReport()->endColumn();
    }

    /**
     * Display dropdown
     * */
    public function displayDropdownCriteria()
    {
        $values = [];
        if (!empty($this->getParameterValue())) {
            $values = $this->getParameterValue();
        }
        $options = ['values' => $values,
            'multiple' => true,
        ];

        if (is_array($this->options) && !empty($this->options)) {
            foreach ($this->options as $key => $value) {
                $options[$key] = $value;
            }
        }

        $items = $this->getItems();
        Dropdown::showFromArray($this->getName(), $items, $options);
        //Dropdown::show($this->getItemType(), $options);
    }

    /**
     * Get SQL code associated with the criteria
     *
     * @see plugins/reports/inc/PluginReportsAutoCriteria::getSqlCriteriasRestriction()
     * */
    public function getSqlCriteriasRestriction($link = 'AND')
    {
        global $DB;

        $raw_value = $this->getParameterValue();

        // The identifiers come straight from the request. Confront them with the entity
        // perimeter of the session before they reach the query: nothing else in this class
        // did, so a forged value pivoted the report onto a row of another entity.
        $value = $this->getReadableParameterValues();
        if (!empty($raw_value) && $value === []) {
            // Every posted identifier was rejected. Match nothing rather than dropping the
            // criteria, which would widen the report instead of narrowing it.
            return $link . " " . $this->getSqlField() . " IN ('-1') ";
        }
        if (!is_array($raw_value)) {
            $value = $value === [] ? $raw_value : reset($value);
        }

        if ($value || $this->searchzero) {
            if (!$this->childrens) {
                // Multi-select posts an array (param[]=x); a single selection arrives as
                // a scalar. Escape every value and build an IN() list so an array can
                // never reach $DB->escape() as-is (it expects a string and would raise a
                // PHP error). The scalar path keeps its original "= 'value'" form.
                if (is_array($value)) {
                    $escaped = [];
                    foreach ($value as $one) {
                        $escaped[] = "'" . $DB->escape((string) $one) . "'";
                    }
                    if (empty($escaped)) {
                        return '';
                    }
                    return $link . " " . $this->getSqlField() . " IN (" . implode(',', $escaped) . ") ";
                }
                return $link . " " . $this->getSqlField() . "='" . $DB->escape((string) $value) . "' ";
            }
            if ($value) {
                // With child resolution, expand every selected id to its descendants.
                // Cast each id to int before getSonsOf() (which expects a single id) so an
                // array value degrades safely, and int-cast the final list before the IN().
                $dbu = new DbUtils();
                $ids = [];
                foreach ((is_array($value) ? $value : [$value]) as $one) {
                    $ids = array_merge($ids, $dbu->getSonsOf($this->getTable(), (int) $one));
                }
                $ids = array_values(array_unique(array_map('intval', $ids)));
                if (empty($ids)) {
                    return '';
                }
                return $link . " " . $this->getSqlField() . " IN (" . implode(',', $ids) . ") ";
            }
            // 0 + its child means ALL
        }
        // Zero => means ALL => no criteria
        return '';
    }

    /**
     * Get SQL code associated with the criteria
     */
    public function getNewSqlCriteriasRestriction($link = 'AND')
    {

        $raw_value = $this->getParameterValue();
        if (empty($raw_value)) {
            return [];
        }

        // Same perimeter control as getSqlCriteriasRestriction(): the posted identifiers are
        // confronted with the entities the session may see before they reach the builder.
        $value = $this->getReadableParameterValues();
        if ($value === []) {
            // Fail closed: match nothing rather than returning no criteria at all.
            return [$this->getSqlField() => -1];
        }
        if (!is_array($raw_value)) {
            $value = reset($value);
        }

        if (!$this->childrens) {
            return [$this->getSqlField() => $value];
        }

        // getSonsOf() takes a single identifier, so a multi-select has to be expanded one
        // value at a time; the free function used here resolved to nothing in this namespace.
        $dbu    = new DbUtils();
        $childs = [];
        foreach ((is_array($value) ? $value : [$value]) as $one) {
            $childs = array_merge($childs, $dbu->getSonsOf($this->getTable(), (int) $one));
        }
        $childs = array_values(array_unique(array_map('intval', $childs)));

        // An empty list renders as "IN ()" and breaks the query, so degrade to a criteria
        // that simply matches nothing.
        return [$this->getSqlField() => $childs === [] ? [-1] : $childs];
    }

    /**
     * Identifiers posted for this criteria that the current session is allowed to read.
     *
     * A table with no entity perimeter (no resolvable itemtype, or one shared by every
     * entity) keeps its values untouched.
     *
     * @return array<int, mixed>
     **/
    private function getReadableParameterValues(): array
    {
        $value = $this->getParameterValue();
        if (empty($value)) {
            return [];
        }

        $values   = is_array($value) ? $value : [$value];
        $itemtype = $this->getItemType();
        if (!$this->hasEntityPerimeter($itemtype)) {
            return array_values($values);
        }

        $readable = [];
        foreach ($values as $one) {
            if (is_scalar($one) && $this->isIdentifierReadable($itemtype, $one)) {
                $readable[] = $one;
            }
        }

        return $readable;
    }
}
