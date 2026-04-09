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
 @authors    Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel, Dévi Balpe
 @copyright Copyright (c) 2009-2022 Reports plugin team
 @license   AGPL License 3.0 or (at your option) any later version
            http://www.gnu.org/licenses/agpl-3.0-standalone.html
 @link      https://forge.glpi-project.org/projects/reports
 @link      http://www.glpi-project.org/
 @since     2009
 --------------------------------------------------------------------------
 */

class PluginReportsReport extends CommonDBTM
{
    /**
     * Return the localized name of the current Type
     * Shoudl be overloaded in each new class
     *
     * @param $nb  integer  for singular / plural
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return _n('Report', 'Reports', $nb);
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

    public static function getReportsTitles()
    {

        $titles['applicationsbylocation'] = __('Applications by locations and versions', 'reports');
        $titles['doublons'] = __('Duplicate computers', 'reports');
        $titles['equipmentbygroups'] = __('List all devices of a group, ordered by users', 'reports');
        $titles['equipmentbylocation'] = __('Number of equipments by location', 'reports');
        $titles['globalhisto'] = __('Global History (for Test / example only)', 'reports');
        $titles['histohard'] = __("History of last hardware's installations", 'reports');
        $titles['histoinst'] = __("History of last software's installations", 'reports');
        $titles['infocom'] = __('Financial information', 'reports');
        $titles['iteminstall'] = __('Time before equipment start-up', 'reports');
        $titles['licenses'] = __('Detailed license report', 'reports');
        $titles['licensesexpires'] = __('Licenses by expiration date', 'reports');
        $titles['listequipmentbylocation'] = __('List of equipments by location', 'reports');
        $titles['listgroups'] = __('List of groups and members', 'reports');
        $titles['location'] = __('Location tree', 'reports');
        $titles['pcsbyentity'] = __('Number of items by entity', 'reports');
        $titles['printers'] = __('Printers', 'reports');
        $titles['rules'] = __("Rule's catalog", 'reports');
        $titles['searchinfocom'] = __('Search in the financial information', 'reports');
        $titles['softnotinstalled'] = __('Detailed report of software installation by status', 'reports');
        $titles['softversioninstallations'] = __('Software version installations', 'reports');
        $titles['statnightticketsbypriority'] = __('Tickets opened at night, sorted by priority', 'reports');
        $titles['statticketsbyentity'] = __('Helpdesk requesters and tickets by entity', 'reports');
        $titles['statticketsbypriority'] = __('Tickets no closed, sorted by priority', 'reports');
        $titles['statusertask'] = __('Tasks list per user', 'reports');
        $titles['transferreditems'] = __('List of transfered objects', 'reports');
        $titles['zombies'] = __('Users with no right', 'reports');

        return $titles;
    }

    public static function setReportsTitles($reports = [])
    {
        $titles = self::getReportsTitles();
        if (count($reports) > 0) {
            foreach ($reports as $report => $title) {
                if (!in_array($report, $titles)) {
                    $titles[$report] = $title;

                }
            }
        }
        $_SESSION['glpi_plugin_reports_reports'] = $titles;

        return $titles;
    }

    public static function getAllReportsTitles()
    {
        $titles = self::getReportsTitles();
        if (isset($_SESSION['glpi_plugin_reports_reports'])) {
            $titles = $_SESSION['glpi_plugin_reports_reports'];
        }


        return $titles;
    }
}
