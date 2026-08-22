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

use GlpiPlugin\Reports\Pluginfield;

header("Content-Type: text/html; charset=UTF-8");
Session::checkRight("config", UPDATE);

$_POST['used'] = $_POST['used'] ?? [];
if (isset($_POST['get_containers_list']) && isset($_POST['itilcategories_id']) && isset($_POST['report'])) {
    $return = "";
    $report = $_POST['report'];
    $pluginfield = new Pluginfield();
    $plugin_field_container = new PluginFieldsContainer();
    $plugin_field_fields = new PluginFieldsField();
    $elements = $pluginfield->find(['report' => $report, 'itilcategories_id' => (int) $_POST['itilcategories_id']]);

    $used = [];
    foreach ($elements as $element) {
        $used[$element['glpi_plugin_fields_containers_id']][] = $element['glpi_plugin_fields_fields_id'];
    }

    $plugin_field_container = new PluginFieldsContainer();
    $plugin_field_fields = new PluginFieldsField();
    $containers = $plugin_field_container->find([]);
    $containers_able = [];
    $containers_able[0] = Dropdown::EMPTY_VALUE;
    foreach ($containers as $container) {
        $types = json_decode($container['itemtypes'], true);
        if (in_array(Ticket::getType(), $types)) {
            $containers_able[$container['id']] = $container['label'];
        }
    }
    $rand = mt_rand();
    $return .= Dropdown::showFromArray(
        'glpi_plugin_fields_containers_id',
        $containers_able,
        ['display' => false, 'rand' => $rand],
    );
    $params = [
        'glpi_plugin_fields_containers_id' => '__VALUE__',
        'get_fields_list' => true,
        'used' => $used,
    ];
    $return .= Ajax::updateItemOnSelectEvent(
        'dropdown_glpi_plugin_fields_containers_id' . $rand,
        'fields_pluingfields',
        PLUGIN_REPORTS_WEBDIR . '/ajax/pluginfieldsfield.php',
        $params,
        false,
    );
    echo $return;
} elseif (isset($_POST['get_fields_list']) && isset($_POST['used']) && isset($_POST['glpi_plugin_fields_containers_id'])) {
    $id_container = (int) $_POST['glpi_plugin_fields_containers_id'];
    $plugin_field_fields = new PluginFieldsField();
    $containers = $plugin_field_fields->find(['plugin_fields_containers_id' => $id_container]);
    $containers_able = [];
    $containers_able[0] = Dropdown::EMPTY_VALUE;
    $used = $_POST['used'];
    foreach ($containers as $container) {
        $containers_able[$container['id']] = $container['label'];
    }
    $values_used = [];
    if (isset($used) && is_array($used) && isset($used[$id_container])) {
        foreach ($used[$id_container] as $u) {
            $values_used[$u] = $u;
        }
    }

    Dropdown::showFromArray('glpi_plugin_fields_fields_id', $containers_able, ['used' => $values_used]);
}
