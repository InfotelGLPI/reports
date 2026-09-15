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
class DropdownCriteria extends AutoCriteria
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
    private $condition = '';


    /**
     * @param $report
     * @param $name
     * @param $tableortype  (default '')
     * @param $label        (default '')
     * @param $condition    (default '')
    **/
    public function __construct($report, $name, $tableortype = '', $label = '', $condition = '')
    {

        parent::__construct($report, $name, $name, $label);

        $dbu = new DbUtils();

        $this->condition = $condition;

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
    **/
    public function getTable()
    {
        return $this->table;
    }


    /**
     * Get criteria's related table
    **/
    public function getItemType()
    {

        $dbu = new DbUtils();
        return $dbu->getItemTypeForTable($this->table);
    }


    /**
     * Will display dropdown childrens (in table in hierarchical)
    **/
    public function setWithChildrens()
    {
        global $CFG_GLPI;

        //if (in_array($this->getTable(), $CFG_GLPI["dropdowntree_tables"])) {
        // TODO find a solution to check is children exists
        $this->childrens = true;
        //}
    }


    /**
     * Will display dropdown childrens (in table in hierarchical)
    **/
    public function setSearchZero()
    {
        $this->searchzero = true;
    }


    /**
     * Set default criteria value to 0 and entity restriction to current entity only
    **/
    public function setDefaultValues()
    {

        $this->addParameter($this->getName(), 0);
        $this->setEntityRestriction($_SESSION["glpiactive_entity"]);
        $this->setDisplayComments();
    }


    /**
     * Show dropdown comments (enable by defaults)
    **/
    public function setDisplayComments()
    {
        $this->displayComments = true;
    }


    /**
     * Hide dropdown comments
    **/
    public function setNoDisplayComments()
    {
        $this->displayComments = false;
    }


    /**
     * Get display comments status
    **/
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
    **/
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
    **/
    public function setEntityRestriction($restriction)
    {
        $dbu = new DbUtils();

        switch ($restriction) {
            case REPORTS_NO_ENTITY_RESTRICTION:
                $this->entity_restrict = -1;
                break;

            case REPORTS_CURRENT_ENTITY:
                $this->entity_restrict = $_SESSION["glpiactive_entity"];
                break;

            case REPORTS_SUB_ENTITIES:
                $this->entity_restrict = $dbu->getSonsOf('glpi_entities', $_SESSION["glpiactive_entity"]);
                break;
        }
    }


    /**
     * Get entity restrict status
    **/
    public function getEntityRestrict()
    {
        return $this->entity_restrict;
    }


    /**
     * Get criteria's subtitle
    **/
    public function getSubName()
    {

        $value = $this->getParameterValue();
        if ($value) {
            // The dropdown is rendered under an entity restriction, but that control protects
            // the listed rows, not the POSTed value: a forged identifier taken from another
            // entity still resolved to its completename here, which leaked the location, group
            // or category nomenclature of entities the caller cannot read -- the result rows
            // stayed empty, only the subtitle talked. Resolve the label only once the target
            // has been confronted to the entity perimeter of the session.
            if (!$this->isValueReadable($value)) {
                return $this->getCriteriaLabel();
            }

            return $this->getCriteriaLabel() . " : " . Dropdown::getDropdownName($this->getTable(), $value);
        }

        if ($this->searchzero) {
            // zero
            return sprintf(__('%1$s: %2$s'), $this->getCriteriaLabel(), __('None'));
        }

        // All
        return '';
    }


    /**
     * Tell whether the value posted for this criteria designates a row the current session
     * is allowed to read, so that its label may be echoed back in the report subtitle.
     *
     * Fails closed: a table with no resolvable itemtype, or a row that no longer exists,
     * yields no label rather than an unchecked one.
     *
     * @param mixed $value identifier posted for the criteria
     **/
    private function isValueReadable($value): bool
    {
        return $this->isIdentifierReadable($this->getItemType(), $value);
    }


    /**
     * Display criteria in the criteria's selection form
    **/
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
    **/
    public function displayDropdownCriteria()
    {

        $options = ['name'     => $this->getName(),
            'value'    => $this->getParameterValue(),
            'comments' => $this->getDisplayComments(),
            'entity'   => $this->getEntityRestrict()];

        if ($this->condition) {
            $options['condition'] = [$this->condition];
        }
        Dropdown::show($this->getItemType(), $options);
    }


    /**
     * Get SQL code associated with the criteria
     *
     * @see plugins/reports/inc/AutoCriteria::getSqlCriteriasRestriction()
    **/
    public function getSqlCriteriasRestriction($link = 'AND')
    {
        global $DB;

        $dbu = new DbUtils();

        if ($this->getParameterValue() || $this->searchzero) {
            if ($this->getParameterValue() && !$this->isValueReadable($this->getParameterValue())) {
                // getSubName() already refuses to echo the label of a row outside the entity
                // perimeter of the session, but the restriction itself was built from the
                // posted identifier whatever it designated. Returning an empty restriction
                // here would WIDEN the result set, so the criteria is kept and made
                // unsatisfiable instead: no dropdown row ever carries the identifier -1.
                return $link . " " . $this->getSqlField() . "='-1' ";
            }
            if (!$this->childrens) {
                // Force a scalar context so an array-typed value (param[]=x) cannot
                // reach $DB->escape(), which expects a string; scalar values are unaffected.
                return $link . " " . $this->getSqlField() . "='" . $DB->escape((string) $this->getParameterValue()) . "' ";
            }
            if ($this->getParameterValue()) {
                // Cast to int before getSonsOf() (which expects a single id) so an
                // array-typed value degrades safely instead of raising a PHP error.
                return $link . " " . $this->getSqlField()
                       . " IN (" . implode(',', $dbu->getSonsOf(
                           $this->getTable(),
                           (int) $this->getParameterValue(),
                       )) . ") ";
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

        if (empty($this->getParameterValue())) {
            return [];
        }

        if (!empty($this->getParameterValue()) || $this->searchzero) {
            if (!$this->isValueReadable($this->getParameterValue())) {
                // Same fail closed reasoning as getSqlCriteriasRestriction(): an identifier
                // the session may not read must not filter the report on a row it cannot see,
                // and dropping the criteria altogether would widen the result set instead of
                // narrowing it. -1 is never a dropdown identifier, so the report comes back
                // empty, which is what the caller is entitled to.
                return [$this->getSqlField() => -1];
            }
            if (!$this->childrens) {
                return [$this->getSqlField() => $this->getParameterValue()];
            }
            if ($this->getParameterValue()) {
                $childs = getSonsOf(
                    $this->getTable(),
                    $this->getParameterValue(),
                );
                return [$this->getSqlField() => $childs];
            }
            // 0 + its child means ALL
        }
    }
}
