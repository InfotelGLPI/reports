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

use Session;

/**
 * class ColumnLink to manage output
 */
class ColumnLink extends Column
{
    private $obj           = null;
    private $with_comment  = 0;
    private $with_navigate = 0;


    public function __construct($name, $title, $itemtype, $options = [])
    {

        parent::__construct($name, $title, $options);

        $this->obj = getItemForItemtype($itemtype);

        if (isset($options['with_comment'])) {
            $this->with_comment = $options['with_comment'];
        }

        if (isset($options['with_navigate'])) {
            $this->with_navigate = $options['with_navigate'];
            Session::initNavigateListItems($this->obj->getType(), _n('Report', 'Reports', 2));
        }
    }


    public function displayValue($output_type, $row)
    {

        if (!isset($row[$this->name]) || !$row[$this->name]) {
            return '';
        }

        // The MySQL driver hands integer columns back as strings depending on the connection
        // settings, so is_int() made the link silently disappear on some installations. Test
        // the shape of the value, then work on the integer.
        if (!is_scalar($row[$this->name]) || !ctype_digit((string) $row[$this->name])) {
            return '';
        }
        $items_id = (int) $row[$this->name];

        if (!$this->obj || !$this->obj->getFromDB($items_id)) {
            return (string) $items_id;
        }

        if ($this->with_navigate) {
            Session::addToNavigateListItems($this->obj->getType(), $items_id);
        }

        if (AutoReport::isHtmlOutputType($output_type) && ($this->obj != null)) {
            return $this->obj->getLink();
        }

        return $this->obj->getNameID();
    }
}
