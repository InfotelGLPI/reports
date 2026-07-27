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

/**
 * class Column to manage output
 */
class Column
{
    // name of the column in the SQL result set
    public $name;
    // Fields for ORDER BY when this column is selected
    public $sorton;
    // Label of the column in the report
    private $title;
    // Extras class for rendering in HTML
    private $extrafine;
    // Extras class for rendering in HTML in Bold
    private $extrabold;
    // Manage total for this colum (if handled by sub-type)
    protected $withtotal;


    public function __construct($name, $title, $options = [])
    {

        $this->name      = $name;
        $this->title     = $title;

        // Extras class for each cell
        $this->extrafine = ($options['extrafine'] ?? '');

        // Extras class for each total cell
        $this->extrabold = ($options['extrabold'] ?? "class='b'");

        // Enable total for this column (if handle bu subtype)
        $this->withtotal = ($options['withtotal'] ?? false);

        // Enable sort for this column
        $this->sorton = ($options['sorton'] ?? false);
    }


    public function showTitle($output, $output_type, &$num)
    {

        if (($output_type != Search::HTML_OUTPUT) || !$this->sorton) {
            //          echo Search::showHeaderItem($output_type,$this->title , $num);
            echo $output::showHeaderItem($this->title, $num);
            return;
        }
        $order = 'ASC';
        $issort = false;
        if (isset($_GET['sort']) && $_GET['sort'] == $this->name) {
            $issort = true;
            if (isset($_GET['order']) && $_GET['order'] == 'ASC') {
                $order = 'DESC';
            }
        }
        $link  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $link .= '?sort=' . urlencode($this->name);
        $link .= '&order=' . $order;

        // Keep only essential parameters (id, report)
        $essential_params = ['id', 'report'];
        foreach ($essential_params as $param) {
            if (isset($_GET[$param])) {
                $link .= '&' . $param . '=' . urlencode($_GET[$param]);
            }
        }

        echo $output::showHeaderItem($this->title, $num, $link, $issort, ($order == 'ASC' ? 'DESC' : 'ASC'));
    }

    public function showHtmlTitle($output, &$num)
    {

        if (!$this->sorton) {
            return $output::showHeaderItem($this->title, $num);
        }
        $order = 'ASC';
        $issort = false;
        if (isset($_GET['sort']) && $_GET['sort'] == $this->name) {
            $issort = true;
            if (isset($_GET['order']) && $_GET['order'] == 'ASC') {
                $order = 'DESC';
            }
        }
        $link  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $link .= '?sort=' . urlencode($this->name);
        $link .= '&order=' . $order;

        // Keep only essential parameters (id, report)
        $essential_params = ['id', 'report'];
        foreach ($essential_params as $param) {
            if (isset($_GET[$param])) {
                $link .= '&' . $param . '=' . urlencode($_GET[$param]);
            }
        }

        return $output::showHeaderItem($this->title, $num, $link, $issort, ($order == 'ASC' ? 'DESC' : 'ASC'));
    }

    public function showExportTitle()
    {
        return $this->title;
    }

    public function showValue($output_type, $row)
    {
        return $this->displayValue($output_type, $row);
    }

//    public function showHtmlValue($output_type, $output, $row, $num, $row_num, $bold = false)
//    {
//        return $output::showItem($this->displayValue($output_type, $row), $num, $row_num, ($bold ? $this->extrabold : $this->extrafine));
//    }
//
//    public function showExportValue()
//    {
//        return $row[$this->name] ?? "";
//    }
//
//
//    public function showTotal($output_type, &$num, $row_num)
//    {
//
//        echo Search::showItem(
//            $output_type,
//            ($this->withtotal ? $this->displayTotal($output_type) : ''),
//            $num,
//            $row_num,
//            $this->extrabold
//        );
//    }

    public function showNewTotal($output_type, &$num, $row_num)
    {

        return ($this->withtotal ? $this->displayTotal($output_type) : '');
    }


    public function displayValue($output_type, $row)
    {
        if (isset($row[$this->name])) {
            // Stored XSS: GLPI 10+/11 stores field values unescaped and the core HTML search
            // output writes this value into a <td> without escaping. Escape free-text DB values
            // for HTML output (leave CSV/PDF/other exports untouched so they are not corrupted).
            if ($output_type == \Search::HTML_OUTPUT) {
                return htmlspecialchars((string) $row[$this->name], ENT_QUOTES);
            }
            return $row[$this->name];
        }
        return '';
    }


    public function displayTotal($output_type)
    {
        return '';
    }
}
