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

use Html;
use Search;

/**
 * class ColumnTimestamp to manage output
 */
class ColumnTimestamp extends Column
{
    private $total;
    private $withsec;


    public function __construct($name, $title, $options = [])
    {

        if (!isset($options['extrafine'])) {
            $options['extrafine'] =  "class='right'";
        }

        if (!isset($options['extrabold'])) {
            $options['extrabold'] =  "class='b right'";
        }

        if (!isset($options['export_timestamp'])) {
            $options['export_timestamp'] =  false;
        }
        // Export with timestamp
        $this->export_timestamp = ($options['export_timestamp'] ?? false);
        // Always display sec ?
        $this->withsec = ($options['withsec'] ?? false);

        parent::__construct($name, $title, $options);

        $this->total = 0;
    }


    public function displayValue($output_type, $row)
    {

        if ($this->export_timestamp) {
            if (isset($row[$this->name])) {
                if ($output_type == Search::HTML_OUTPUT) {

                    $this->total += intval($row[$this->name]);
                    return Html::timestampToString($row[$this->name], $this->withsec);

                }
                $this->total += intval($row[$this->name]);
                return $row[$this->name];
            }

        } elseif (isset($row[$this->name])) {

            $this->total += intval($row[$this->name]);
            return Html::timestampToString($row[$this->name], $this->withsec);
        }
        return '';
    }


    public function displayTotal($output_type)
    {
        if ($this->export_timestamp) {
            return $this->total;
        }
        return Html::timestampToString($this->total, $this->withsec);
    }
}
