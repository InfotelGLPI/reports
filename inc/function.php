<?php

/**
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
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel, Alexandre Delaunay
 @copyright Copyright (c) 2009-2022 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
*/


/**
 * Search for reports in all activated plugins
 *
 * @return array - an array which contains all the reports found (name => plugin)
**/
function searchReport($all = false)
{
    global $DB;

    $tab = [];
    $filter = ['FROM' => 'glpi_plugins',
        'WHERE' => ['state' => Plugin::ACTIVATED]];
    if ($all) {
        $filter = "";
    }
    foreach ($DB->request($filter) as $plug) {
        foreach (glob(Plugin::getPhpDir($plug['directory']) . "/report/*", GLOB_ONLYDIR) as $path) {
            $tab[basename($path)] = $plug['directory'];
            includeLocales(basename($path), $plug['directory']);
        }
    }

    return $tab;
}


/**
 * Include locales for a specific report
 *
 * @param $report_name  - the name of the report to use
 * @param $plugin       - plugins name (default 'reports')
 *
 * @return boolean, true if locale found
**/
function includeLocales($report_name, $plugin = 'reports')
{
    global $LANG;

    $plugin_key = 'plugin_' . $plugin;

    if (!isset($LANG[$plugin_key])) {
        $LANG[$plugin_key] = [];
    }

    $locale_loaded = false;

    // Ensure $LANG entry is set with the report title from translation
    if (!isset($LANG[$plugin_key][$report_name])) {
        $name = $report_name . '_report_title';

        $LANG[$plugin_key][$report_name] = __($name, $plugin);
        // For dev: log if translation key not found
        if (($_SESSION['glpi_use_mode'] == Session::DEBUG_MODE)
            && ($LANG[$plugin_key][$report_name] == $name)) {
            Toolbox::logInFile(
                'php-errors',
                "includeLocales($name, $plugin) => translation key not found\n"
            );
        }
    }

    return $locale_loaded;
}


function getPriorityLabelsArray()
{

    return ["1" => Ticket::getPriorityName(1),
        "2" => Ticket::getPriorityName(2),
        "3" => Ticket::getPriorityName(3),
        "4" => Ticket::getPriorityName(4),
        "5" => Ticket::getPriorityName(5),
        "6" => Ticket::getPriorityName(6)];
}


function getImpactLabelsArray()
{

    return ["1" => Ticket::getImpactName(1),
        "2" => Ticket::getImpactName(2),
        "3" => Ticket::getImpactName(3),
        "4" => Ticket::getImpactName(4),
        "5" => Ticket::getImpactName(5)];
}


function getUrgencyLabelsArray()
{

    return ["1" => Ticket::getUrgencyName(1),
        "2" => Ticket::getUrgencyName(2),
        "3" => Ticket::getUrgencyName(3),
        "4" => Ticket::getUrgencyName(4),
        "5" => Ticket::getUrgencyName(5)];
}
