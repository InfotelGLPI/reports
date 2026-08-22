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
 * class ColumnItemCheckbox to manage output
 */
class ColumnItemCheckbox extends Column
{
    private $obj          = null;
    private $with_comment = 0;


    public function __construct($name, $itemtype, $options = [])
    {

        parent::__construct($name, '&nbsp;', $options);

        $this->obj = getItemForItemtype($itemtype);
    }


    public function showHtmlTitle($output, &$num)
    {

        echo $output::showHeaderItem(Html::getCheckAllAsCheckbox('massform' . get_class($this->obj)), $num);

    }

    public function showExportTitle() {}


    public function displayValue($output_type, $row)
    {

        if (!isset($row[$this->name]) || !$row[$this->name]) {
            return '';
        }
        if ($this->obj
            && ($output_type == Search::HTML_OUTPUT)
            && $this->obj->can($row[$this->name], UPDATE)) {
            return Html::getMassiveActionCheckBox(get_class($this->obj), $row[$this->name]);
        }

        return '';
    }
}
