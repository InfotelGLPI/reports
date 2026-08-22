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

use Glpi\DBAL\QueryExpression;
use Search;

/**
 * User titles selection criteria
 */
class TextCriteria extends DropdownCriteria
{
    /**
     * @param $report
     * @param $name      (default 'value')
     * @param $label     (default '')
    **/
    public function __construct($report, $name = 'value', $label = '')
    {

        parent::__construct($report, $name, NOT_AVAILABLE, ($label ? $label : __('Name')));
    }

    public function setDefaultValues()
    {
        $this->addParameter($this->getName(), '');
    }

    public function displayCriteria()
    {

        $this->getReport()->startColumn();
        echo $this->getCriteriaLabel() . '&nbsp;:';
        $this->getReport()->endColumn();

        $this->getReport()->startColumn();
        echo "<input type='text' name='" . $this->getName() . "' value='" . htmlescape($this->getParameterValue()) . "'>";
        $this->getReport()->endColumn();
    }

    /**
     * Get criteria's subtitle
    **/
    public function getSubName()
    {

        $param = $this->getParameterValue();
        if ($param) {
            return $this->getCriteriaLabel() . ' : ' . $this->getParameterValue();
        }
        return '';
    }

    public function getSqlCriteriasRestriction($link = 'AND')
    {

        $param = $this->getParameterValue();
        if ($param) {
            return Search::makeTextCriteria($this->getSqlField(), $param, false, $link);
        }
        return '';
    }

    /**
     * Get SQL code associated with the criteria
     */
    public function getNewSqlCriteriasRestriction($link = 'AND')
    {
        global $DB;

        $param = $this->getParameterValue();
        if (!empty($param)) {
            return [new QueryExpression(Search::makeTextCriteria($DB::quoteName($this->getSqlField()), $param))];
        }
    }

}
