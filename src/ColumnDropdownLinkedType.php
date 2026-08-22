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
use Search;

/**
 * class ColumnDropdownLinkedType to manage output
 */
#[AllowDynamicProperties]
class ColumnDropdownLinkedType extends Column
{
    private $obj          = null;
    private $with_comment = 0;
    private $nametype     = '';
    private $type_suffix  = '';


    public function __construct($nameid, $title, $nametype, $suffix = '', $options = [])
    {

        parent::__construct($nameid, $title, $options);

        $this->nametype = $nametype;
        $this->suffix = $suffix;
        if (isset($options['with_comment'])) {
            $this->with_comment = $options['with_comment'];
        }
    }


    public function displayValue($output_type, $row)
    {

        if (!isset($row[$this->name]) || !$row[$this->name]) {
            return '';
        }

        if (isset($row[$this->nametype])
          && $row[$this->nametype]
          && (is_null($this->obj) || get_class($this->obj) != $row[$this->nametype])) {
            $objname   = $row[$this->nametype] . $this->suffix;
            // Validate the DB-derived itemtype at the sink: only instantiate a real
            // CommonDBTM subclass, so an unexpected/legacy column value cannot trigger
            // instantiation of an arbitrary class or a fatal error.
            if (!is_a($objname, \CommonDBTM::class, true)) {
                return $row[$this->name];
            }
            $this->obj = new $objname();
        }

        if (!$this->obj || !$this->obj->getFromDB($row[$this->name])) {
            return $row[$this->name];
        }

        if ($output_type == Search::HTML_OUTPUT) {
            return $this->obj->getLink();
        }

        return $this->obj->getNameID();
    }
}
