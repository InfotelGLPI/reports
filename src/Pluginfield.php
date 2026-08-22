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

use Ajax;
use CommonDBTM;
use Dropdown;
use Html;
use ITILCategory;
use PluginFieldsContainer;
use PluginFieldsField;
use Search;
use Session;
use Ticket;

class Pluginfield extends CommonDBTM
{
    /**
     * Return the localized name of the current Type
     * Shoudl be overloaded in each new class
     *
     * @param $nb  integer  for singular / plural
     *
     * @return string
     */

    public static $rightname = "config";

    public static function getTypeName($nb = 0)
    {
        return _n('Report', 'Reports', $nb);
    }

    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [UPDATE]);
    }

    public static function canView(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, READ);
        }
        return false;
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canUpdateItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public static function canDelete(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canDeleteItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public static function canPurge(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public function canCreateItem(): bool
    {

        return Session::haveRight(self::$rightname, UPDATE);
    }

    /**
     * Get rights for an item _ may be overload by object
     *
     * @since version 0.85
     *
     * @param $interface   string   (defalt 'central')
     *
     * @return array of rights to display
    **/
    public function getRights($interface = 'central')
    {
        return [READ => __('Read')];
    }

    public function showOptions($report)
    {
        $plugin_field_container = new PluginFieldsContainer();
        $plugin_field_fields = new PluginFieldsField();
        $elements = $this->find(['report' => $report]);

        $used = [];
        foreach ($elements as $element) {
            $used[$element['glpi_plugin_fields_containers_id']][] = $element['glpi_plugin_fields_fields_id'];
        }

        $this->formSelector($used, $report);

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_2'>";
        echo "<td class='center' colspan='" . (2 * 2) . "'>";
        echo "<div class='left'>";
        // Set display type for export if define
        $output_type = Search::HTML_OUTPUT;

        if (isset($_GET["display_type"])) {
            $output_type = $_GET["display_type"];
        }
        $nbcols        = 2;
        $header_num    = 1;
        $row_num       = 1;
        $num_row_final = count($elements);
        // Column headers
        $rand = mt_rand();
        $massformid = 'massform' . self::getType() . $rand;
        Html::openMassiveActionsForm($massformid);
        $massiveactionparams = ['container' => $massformid];

        Html::showMassiveActions($massiveactionparams);

        //      Html::showMassiveActions($massiveactionparams);

        echo Search::showHeader($output_type, $num_row_final, $nbcols, 1);
        echo Search::showNewLine($output_type);
        echo Search::showHeaderItem(
            $output_type,
            Html::getCheckAllAsCheckbox($massformid),
            $header_num,
            "",
            0,
        );
        echo Search::showHeaderItem($output_type, PluginFieldsContainer::getTypeName(), $header_num);

        echo Search::showHeaderItem($output_type, PluginFieldsField::getTypeName(), $header_num);
        echo Search::showHeaderItem($output_type, ITILCategory::getTypeName(), $header_num);

        echo Search::showEndLine($output_type);

        foreach ($elements as $key => $value) {
            echo Search::showNewLine($output_type);
            echo Search::showItem($output_type, Html::getMassiveActionCheckBox($this->getType(), $value['id'], ['class' => $this]), $item_num, $row_num);
            $plugin_field_container->getFromDB($value['glpi_plugin_fields_containers_id']);
            $plugin_field_fields->getFromDB($value['glpi_plugin_fields_fields_id']);
            // PluginFields labels and the itilcategory name are stored raw in the
            // DB (GLPI 10+), so escape them before Search::showItem() writes them
            // into a <td> without htmlspecialchars (same pattern as Column::displayValue).
            echo Search::showItem($output_type, htmlescape($plugin_field_container->fields['label']), $item_num, $row_num);
            echo Search::showItem($output_type, htmlescape($plugin_field_fields->fields['label']), $item_num, $row_num);
            echo Search::showItem($output_type, htmlescape(Dropdown::getDropdownName('glpi_itilcategories', $value['itilcategories_id'])), $item_num, $row_num);
            echo Search::showEndLine($output_type);
        }
        echo Search::showNewLine($output_type);
        echo Search::showHeaderItem(
            $output_type,
            Html::getCheckAllAsCheckbox($massformid),
            $header_num,
            "",
            0,
        );
        echo Search::showHeaderItem($output_type, PluginFieldsContainer::getTypeName(), $header_num);

        echo Search::showHeaderItem($output_type, PluginFieldsField::getTypeName(), $header_num);
        echo Search::showHeaderItem($output_type, ITILCategory::getTypeName(), $header_num);

        echo Search::showEndLine($output_type);

        $massiveactionparams['ontop'] = false;
        echo "</div>";
        echo "</div>";
        //       Html::showMassiveActions($massiveactionparams);

        echo Search::showFooter($output_type);
        echo "</div>";
        echo "</td></tr>";

        //       Html::closeForm();

    }

    public function formSelector($used, $report)
    {
        global $CFG_GLPI;
        $this->initForm(-1, []);
        $this->showFormHeader();
        $plugin_field_container = new PluginFieldsContainer();
        $plugin_field_fields = new PluginFieldsField();
        $containers = $plugin_field_container->find([]);
        $containers_able =  [];
        $containers_able[0] =  Dropdown::EMPTY_VALUE;
        foreach ($containers as $container) {
            $types = json_decode($container['itemtypes'], true);
            if (in_array(Ticket::getType(), $types)) {
                $containers_able[$container['id']] = $container['name'];
            }
        }
        echo "<tr class='tab_bg_1'><td>";
        echo ITILCategory::getTypeName();
        echo"</td><td>";
        $rand = mt_rand();
        ITILCategory::dropdown(['rand' => $rand]);
        //        $rand = Dropdown::showFromArray('glpi_plugin_fields_containers_id',$containers_able);
        $params = ['itilcategories_id' => '__VALUE__',
            'get_containers_list' => true,
            'report' => $report,
        ];
        Ajax::updateItemOnSelectEvent('dropdown_itilcategories_id' . $rand, 'container_pluingfields', PLUGIN_REPORTS_WEBDIR . '/ajax/pluginfieldsfield.php', $params);
        echo"</td><td>";

        echo"</td><td>";

        echo"</td></tr>";

        echo "<tr ><td>";
        echo PluginFieldsContainer::getTypeName();
        echo Html::hidden('report_name', ['value' => $report]);
        echo"</td><td>";
        echo "<span id='container_pluingfields'>";
        //        $rand = Dropdown::showFromArray('glpi_plugin_fields_containers_id',$containers_able);
        //        $params = ['glpi_plugin_fields_containers_id' => '__VALUE__',
        //                   'get_fields_list' => true,
        //                   'used' => $used,
        //                  ];
        //       Ajax::updateItemOnSelectEvent('dropdown_glpi_plugin_fields_containers_id'.$rand,'fields_pluingfields',$CFG_GLPI['url_base']."/".PLUGIN_REPORTS_NOTFULL_WEBDIR.'/ajax/pluginfieldsfield.php',$params);
        echo "</span>";
        echo"</td><td>";
        echo PluginFieldsField::getTypeName();
        echo"</td><td>";
        echo "<span id='fields_pluingfields'>";

        echo "</span>";

        echo"</td></tr>";
        echo"<tr><td colspan='4'>";
        $this->showFormButtons([]);
        echo"</td></tr>";
        Html::closeForm();
    }
}
