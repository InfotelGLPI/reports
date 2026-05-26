<?php

/*
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
 @authors   Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
 @copyright Copyright (c) 2009-2026 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://github.com/InfotelGLPI/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Reports;

use Dropdown;

/**
 * Dropdown for softwares with license
 */
class SoftwareWithLicenseCriteria extends DropdownCriteria
{


   /**
    * @param $report
    * @param $name      (default 'softwares_id')
    * @param $label     (default '')
   **/
    function __construct($report, $name = 'softwares_id', $label = '')
    {

        parent::__construct(
            $report,
            $name,
            'glpi_softwares',
            ($label ? $label : _n('Software', 'Software', 1))
        );
    }


    function displayDropdownCriteria()
    {
        global $DB;

        $criteria = [
            'SELECT' => [
                'glpi_softwares.id',
                'glpi_softwares.name',
            ],
            'FROM' => 'glpi_softwarelicenses',
            'LEFT JOIN'       => [
                'glpi_softwares' => [
                    'ON' => [
                        'glpi_softwarelicenses' => 'softwares_id',
                        'glpi_softwares'          => 'id',
                    ],
                ],
            ],
            'WHERE' => [],
            'GROUPBY' => ['glpi_softwares.name'],
        ];

        $criteria['WHERE'] = $criteria['WHERE'] + getEntitiesRestrictCriteria(
                'glpi_softwarelicenses'
            );
        $iterator = $DB->request($criteria);

        $temp[0] = Dropdown::EMPTY_VALUE;
        if (count($iterator) > 0) {
            foreach ($iterator as $data) {
                $temp[$data["id"]] = $data['name'];
            }

            $params = [
                "name" => $this->getName(),
                "value" => $this->getParameterValue(),
            ];

            Dropdown::showFromArray($this->getName(), $temp, $params);

        } else {
            echo "<div class='alert alert-danger center'>" . __('No results found') . "</div>";
        }
    }
}
