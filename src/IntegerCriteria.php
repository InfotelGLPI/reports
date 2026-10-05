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

use Dropdown;

/**
 * Integer criteria
 */
class IntegerCriteria extends DropdownCriteria
{
    private $signe = '=';
    private $min   = 0;
    private $max   = 100;
    private $coef  = 1;

    /**
     * @param $report
     * @param $name            (default 'value')
     * @param $label           (default '')
     * @param $signe           (default '')
     * @param $min             (default 0)
     * @param $max             (default 100)
     * @param $coef            (default 1)
     * @param $unit            (default '')
    **/
    public function __construct(
        $report,
        $name = 'value',
        $label = '',
        $signe = '',
        $min = 0,
        $max = 100,
        $coef = 1,
        $unit = ''
    ) {

        parent::__construct($report, $name, NOT_AVAILABLE, ($label ? $label : __('Value')));

        $this->setOptions($signe, $min, $max, $coef, $unit);
    }

    public function setDefaultValues()
    {

        $this->addParameter($this->getName(), 0);
        $this->addParameter($this->getName() . '_sign', '<=');
    }

    /**
     * @param $signe     (default '')
     * @param $min       (default 0)
     * @param $max       (default 100)
     * @param $coef      (default 1)
     * @param $unit      (default '')
    **/
    public function setOptions($signe = '', $min = 0, $max = 100, $coef = 1, $unit = '')
    {

        $this->signe = $signe;
        $this->min   = $min;
        $this->max   = $max;
        $this->coef  = $coef;
        $this->unit  = $unit;
    }

    /**
     * Cells of the criteria: an optional comparison sign, the number, and its unit.
    **/
    public function getCriteriaFields()
    {
        $field = '';
        if (empty($this->signe)) {
            $field .= Dropdown::showFromArray(
                $this->getName() . "_sign",
                ['<='    => '<=',
                    '>='    => '>='],
                ['value' => $this->getParameter($this->getName() . "_sign"), 'display' => false],
            ) . '&nbsp;';
        }
        $opt = ['value'   => $this->getParameterValue(),
            'min'     => $this->min,
            'max'     => $this->max,
            'step'    => 1,
            'display' => false,
        ];
        $field .= Dropdown::showNumber($this->getName(), $opt);

        return [[
            'name'   => $this->getName(),
            'label'  => $this->getCriteriaLabel(),
            'field'  => $field,
            'suffix' => (string) $this->unit,
        ]];
    }


    public function displayCriteria()
    {
        $this->displayCriteriaFields();
    }

    /**
     * Get criteria's subtitle
    **/
    public function getSubName()
    {

        $value = $this->getParameterValue();
        return $this->getCriteriaLabel() . ' ' . $this->getSign() . " $value " . $this->unit;
    }

    public function getSign()
    {

        if (empty($this->signe)) {
            // The sign is used as a raw SQL operator, so restrict the user-supplied value to a
            // strict whitelist to prevent SQL injection through the "<name>_sign" parameter.
            $sign = $this->getParameter($this->getName() . "_sign");
            return in_array($sign, ['<=', '>=', '<', '>', '='], true) ? $sign : '=';
        }
        return $this->signe;
    }

    /**
     * @see plugins/reports/inc/DropdownCriteria::getSqlCriteriasRestriction()
     *
     * @deprecated Kept for third-party reports that still concatenate SQL. Prefer
     *             getNewSqlCriteriasRestriction(): its array criteria are quoted by
     *             $DB->request() itself.
    **/
    public function getSqlCriteriasRestriction($link = 'AND')
    {
        global $DB;

        // Cast to int: this criterion is always numeric, and a value posted as an
        // array (param[]=x) would otherwise hit the "$param * coef" arithmetic and
        // raise a PHP TypeError instead of degrading to a harmless scalar.
        $param = (int) $this->getParameterValue();
        return $link . " " . $DB::quoteName($this->getSqlField()) . $this->getSign() . "'" . ($param * $this->coef) . "' ";
    }

    /**
     * Get SQL code associated with the criteria
     */
    public function getNewSqlCriteriasRestriction($link = 'AND')
    {

        // Same cast as the legacy branch above, for the same reason: getParameterValue() reads
        // $_POST, and a value posted as an array (criteria_name[]=1) would reach the
        // "$param * coef" arithmetic and raise a TypeError instead of degrading to a harmless
        // scalar.
        $param = (int) $this->getParameterValue();
        return [$this->getSqlField() => [$this->getSign(), ($param * $this->coef)]];
    }

}
