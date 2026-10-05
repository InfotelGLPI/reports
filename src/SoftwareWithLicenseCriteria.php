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
use Glpi\Application\View\TemplateRenderer;

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
    public function __construct($report, $name = 'softwares_id', $label = '')
    {

        parent::__construct(
            $report,
            $name,
            'glpi_softwares',
            ($label ? $label : _n('Software', 'Software', 1)),
        );
    }


    /**
     * Cells of the criteria: the select of the softwares that have a licence, or a message when
     * there is none.
    **/
    public function getCriteriaFields()
    {
        $items = $this->getSoftwaresWithLicense();

        return [[
            'name'    => $this->getName(),
            'label'   => $this->getCriteriaLabel(),
            'field'   => $items === [] ? '' : $this->getDropdownField(),
            'message' => $items === [] ? __('No results found') : '',
        ]];
    }


    public function getDropdownField(): string
    {
        $items = $this->getSoftwaresWithLicense();
        if ($items === []) {
            return '';
        }

        return (string) Dropdown::showFromArray(
            $this->getName(),
            [0 => Dropdown::EMPTY_VALUE] + $items,
            [
                'value'   => $this->getParameterValue(),
                'display' => false,
            ],
        );
    }


    /**
     * Softwares covered by at least one licence of the active entities.
     *
     * @return array<int, string> software id => name
    **/
    private function getSoftwaresWithLicense(): array
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

        // glpi_softwarelicenses is recursive and getEntitiesRestrictCriteria() does not infer it
        // from the table name: without the fourth argument, a licence shared down from a parent
        // entity was excluded, so the softwares it covers were proposed as "without licence" by
        // this criterion. Same reasoning as listgroups.php.
        $criteria['WHERE'][] = getEntitiesRestrictCriteria(
            'glpi_softwarelicenses',
            '',
            '',
            true,
        );
        $items = [];
        foreach ($DB->request($criteria) as $data) {
            $items[(int) $data["id"]] = (string) $data['name'];
        }

        return $items;
    }


    /**
     * Display dropdown (legacy API, kept for its callers)
    **/
    public function displayDropdownCriteria()
    {
        $cell = $this->getCriteriaFields()[0];
        TemplateRenderer::getInstance()->display('@reports/autoreport/criteria_cell.html.twig', [
            'field'   => $cell['field'],
            'message' => $cell['message'],
        ]);
    }
}
