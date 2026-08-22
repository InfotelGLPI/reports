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
 * Request titles selection criteria
**/
class RequestTypeCriteria extends DropdownCriteria
{
    /**
     * @param $report
     * @param $name      (default 'requesttypes_id')
     * @param $label     (default '')
    **/
    public function __construct($report, $name = 'requesttypes_id', $label = '')
    {

        parent::__construct(
            $report,
            $name,
            NOT_AVAILABLE,
            ($label ? $label : _n('Request source', 'Request sources', 1)),
        );
    }


    //Dropdown priorities is not a generic dropdown, so the function needs to be overwritten
    public function displayDropdownCriteria()
    {

        Dropdown::show('RequestType', ['name'  => $this->getName(),
            'value' => $this->getParameterValue()]);
    }


    public function getSubName()
    {

        //      if ($this->getParameterValue() > 0) {
        //         return " ".$this->getCriteriaLabel()." : ".getRequestTypeName($this->getParameterValue());
        //      }
    }


    public function setDefaultValues()
    {
        $this->addParameter($this->getName(), 1);
    }

}
