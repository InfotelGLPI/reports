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

use GlpiPlugin\Reports\Profile;
use GlpiPlugin\Reports\Report;
use GlpiPlugin\Reports\Stat;

define("REPORTS_NO_ENTITY_RESTRICTION", 0);
define("REPORTS_CURRENT_ENTITY", 1);
define("REPORTS_SUB_ENTITIES", 2);

define('PLUGIN_REPORTS_VERSION', '2.0.2');

function plugin_init_reports()
{
    global $PLUGIN_HOOKS, $LANG;

    $PLUGIN_HOOKS['csrf_compliant']['reports'] = true;

   //Define only for bookmarks
    Plugin::registerClass(Report::class);

    Plugin::registerClass(Stat::class);

    Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

    if (Session::haveRight("config", UPDATE)) {
        $PLUGIN_HOOKS['config_page']['reports']     = 'front/report.form.php';
    }

    $PLUGIN_HOOKS['menu_entry']['reports'] = false;

    $reports_titles = Report::getAllReportsTitles();

    foreach (Report::searchReport() as $report => $plug) {
        $field = 'plugin_reports_'.$report;
        if ($plug != 'reports') {
            $field = 'plugin_reports_'.$plug."_".$report;
        }
        if (Session::haveRight($field, READ)) {

            if (isset($reports_titles[$report])) {
                $tmp = $reports_titles[$report];
            } else {
                $tmp = $LANG["plugin_$plug"][$report];
            }

           //If the report's name contains 'stat' then display it in the statistics page
           //(instead of Report page)
            if (isStat($report)) {
                if (!isset($PLUGIN_HOOKS['stats'][$plug])) {
                    $PLUGIN_HOOKS['stats'][$plug] = [];
                }
                $PLUGIN_HOOKS['stats'][$plug]["report/$report/$report.php"] = $tmp;
            } else {
                if (!isset($PLUGIN_HOOKS['reports'][$plug])) {
                    $PLUGIN_HOOKS['reports'][$plug] = [];
                }
                $PLUGIN_HOOKS['reports'][$plug]["report/$report/$report.php"] = $tmp;
            }
        }
    }
}


/**
 * Indicate if the report must be displayed in reports or statistics menu
 * @param $report_name string name of the report
 * @return false if it's a stat, false if it's a report
 */
function isStat($report_name)
{

    if (strpos($report_name, 'stat') !== false) {
        return true;
    }
    return false;
}


function plugin_version_reports()
{

    return ['name'           => _n('Report', 'Reports', 2),
           'version'        => PLUGIN_REPORTS_VERSION,
           'author'         => "<a href='https://blogglpi.infotel.com'>Infotel</a>, Xavier CAILLAUD, Nelly MAHU-LASSON, Remi COLLET",
           'license'        => 'GPLv3+',
           'homepage'       => 'https://github.com/InfotelGLPI/reports',
           'minGlpiVersion' => '11.0.0',
           'requirements'   => ['glpi' => ['min' => '11.0.0',
                                           'max' => '12.0.0']]];
}
