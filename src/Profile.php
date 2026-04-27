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

namespace GlpiPlugin\Reports;

use CommonGLPI;
use Html;
use Migration;
use ProfileRight;
use Session;

class Profile extends \Profile
{
    public static $rightname = 'profile';

    /**
     * @param $prof   Profile object
    **/
    public static function showForProfile(\Profile $prof)
    {

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);

        if ($canedit) {
            echo "<form method='post' action='" . $prof->getFormURL() . "'>";
        }

        $rights = self::getAllRights();
        $prof->displayRightsChoiceMatrix(
            $rights,
            ['canedit'       => $canedit,
                'default_class' => 'tab_bg_2',
                'title'         => __(
                    'Rights management by profile',
                    'reports'
                )]
        );
        if ($canedit) {
            echo "<div class='center'>";
            echo Html::hidden('id', ['value' => $prof->getField('id')]);
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>\n";
            Html::closeForm();
        }
        echo "</div>";
    }


    /**
     * @param $report
    **/
    public static function showForReport($report)
    {
        global $DB;

        /* call from front/config.form.php
        * $report = "bar" (from reports) or "foo_bar" (other plugins)
         */
        if (empty($report) || !Session::haveRight('profile', READ)) {
            return false;
        }
        $current = self::getAllProfilesRights(['name' => 'plugin_reports_'.$report]);
        $canedit = Session::haveRight('profile', UPDATE);

        if ($canedit) {
            echo "<form action='" . $_SERVER['PHP_SELF'] . "' method='post'>\n";
        }

        echo "<br><table class='tab_cadre_fixehov'>\n";
        echo "<tr><th colspan='2'>" . __('Profiles rights', 'reports') . "</th></tr>\n";

        foreach ($DB->request(
            ['SELECT' => ['id', 'name'],
            'FROM' => 'glpi_profiles',
            'ORDER'  => 'name']) as $data) {
            echo "<tr class='tab_bg_2'><td>" . $data['name'] . "&nbsp: </td><td>";

            $profrights = ProfileRight::getProfileRights($data['id'], ['statistic', 'reports']);
            $canstat    = (isset($profrights['statistic']) && $profrights['statistic']);
            $canreport  = (isset($profrights['reports'])   && $profrights['reports']);

            if ((isStat($report) && $canstat)
             || (!isStat($report) && $canreport)) {
                \Profile::dropdownRight(
                    $data['id'],
                    ['value'    => ($current[$data['id']] ?? 0),
                        'nonone'  => 0,
                        'noread'  => 0,
                        'nowrite' => 1]
                );
            } else {
                // Can't access because missing right from GLPI core
                echo Html::hidden($data['id'], ['value' => 'NULL']);
                echo __('No access') . " *";
            }
            echo "</td></tr>\n";
        }
        echo "<tr class='tab_bg_2'><td colspan='2'>* ";
        if (isStat($report)) {
            echo __('No right on Assistance / Statistics', 'reports');
        } else {
            echo __('No right on Tools / Reports', 'reports');
        }
        echo "</tr>";

        if ($canedit) {
            echo "<tr class='tab_bg_1'><td colspan='2' class='center'>";
            echo Html::hidden('report', ['value' => $report]);
            echo Html::submit(_sx('button', 'Update'), ['name' => 'update',
                'class' => 'btn btn-primary']);
            echo "</td></tr>\n";
            echo "</table>\n";
            Html::closeForm();
        } else {
            echo "</table>\n";
        }
    }

    /**
     * @param $input
    **/
    public static function updateForReport($input)
    {

        /* call from front/config.form.php
        * $report = "bar" (from reports) or "foo_bar" (other plugins)
         */
        $prof      = new ProfileRight();
        $report    = $input['report'];
        $rightname = "plugin_reports_$report";
        $current   = self::getAllProfilesRights(['name' => $rightname], true);

        foreach ($input as $profiles_id => $right) {
            if ($right == 'NULL') {
                $right = 0;
            }
            if (is_numeric($profiles_id)) {
                if (isset($current[$profiles_id])) {
                    $prof->update(['id'     => $current[$profiles_id]['id'],
                        'rights' => $right]);
                } elseif ($right) {
                    $prof->add(['profiles_id' => $profiles_id,
                        'name'        => $rightname,
                        'rights'      => $right]);
                }
                // TODO Check here with another plugin
            }
        }
    }


    /**
     * @param $reports
    **/
    public function updateRights($reports)
    {
        global $DB;

        $profile_right = new ProfileRight();

        $rights = [];
        foreach ($reports as $report => $plug) {
            if ($plug == 'reports') {
                $rights["plugin_reports_$report"] = 1;
            } else {
                $rights["plugin_reports_{$plug}_{$report}"] = 1;
            }
        }

        $current_rights = [];
        foreach ($DB->request(['SELECT'   => 'name',
            'DISTINCT' => true,
            'FROM' => 'glpi_profilerights',
            'WHERE'    => ['name' => ['LIKE', 'plugin_reports_%']]]) as $data) {
            $current_rights[$data['name']] = 1;
        }

        // Remove old reports
        foreach ($current_rights as $right => $value) {
            if (!isset($rights[$right])) {
                // Delete the lines for old reports
                $profile_right->deleteByCriteria(['name' => $right]);
            } else {
                unset($rights[$right]);
            }
        }
        /*
              // Add new reports
              $rights_name = array_keys($rights);
              ProfileRight::addProfileRights($rights_name);
              if ($_SESSION['glpiactiveprofile']['id'] == 4) {
                 $profile_right->updateProfileRights(4, $rights);
               }*/
    }


    /**
     * @param $crit
     * @param $full   (false by default)
    **/
    public static function getAllProfilesRights($crit, $full = false)
    {
        global $DB;

        $tab = [];

        foreach ($DB->request(['SELECT' => ['*'],
            'FROM' => 'glpi_profilerights',
            'WHERE'  => $crit]) as $data) {
            $tab[$data['profiles_id']] = ($full ? $data : $data['rights']);
        }
        return $tab;
    }


    public static function getAllRights()
    {
        global $LANG;

        $rights = [];

        $reports_names = Report::getAllReportsTitles();

        $reports = Report::searchReport();

        foreach ($reports as $key => $plug) {

            if (!isset($plugname[$plug])) {
                // Retrieve the plugin name
                $function         = "plugin_version_$plug";
                $tmp              = $function();
                $plugname[$plug]  = $tmp['name'];
            }
            $field = 'plugin_reports_' . $key;
            if ($plug != 'reports') {
                $field = 'plugin_reports_' . $plug . "_" . $key;
            }

            if (isset($reports_names[$key])) {
                $rights[] = ['itemtype' => 'GlpiPlugin\Reports\Report',
                    'label'    => $plugname[$plug] . " - " . $reports_names[$key] ?? $key,
                    'field'    => $field];
            } else {
                $rights[] = ['itemtype' => 'GlpiPlugin\Reports\Report',
                    'label'    => $plugname[$plug] . " - " . $LANG["plugin_$plug"][$key],
                    'field'    => $field];
            }
        }
        return $rights;
    }


    /**
     * Look for all the plugins, and update rights if necessary
     */
    public function updatePluginRights()
    {

        $tab = Report::searchReport();
        $this->updateRights($tab);

        return $tab;
    }


    public static function install(Migration $mig)
    {
//        global $DB;
//
//
//        if ($DB->tableExists('glpi_plugin_reports_profiles')) {
//            if ($DB->fieldExists('glpi_plugin_reports_profiles', 'ID')) { // version installee < 1.4.0
//                $query = "ALTER TABLE `glpi_plugin_reports_profiles`
//                      CHANGE `ID` `id` int(11) NOT NULL auto_increment";
//                $DB->doQuery($query, "CHANGE ID: " . $DB->error());
//            }
//
//            if (!$DB->fieldExists('glpi_plugin_reports_profiles', 'profiles_id')) { // version < 1.5.0
//                $mig->renameTable('glpi_plugin_reports_profiles', 'glpi_plugin_reports_oldprofiles');
//                $mig->executeMigration();
//
//                $fields = $DB->listFields('glpi_plugin_reports_oldprofiles');
//                unset($fields['id']);
//                unset($fields['profile']);
//                foreach ($fields as $field => $descr) {
//                    $query = "INSERT INTO `glpi_plugin_reports_profiles`
//                                (`profiles_id`, `report`, `access`)
//                          SELECT `id`, '$field', `$field`
//                          FROM `glpi_plugin_reports_oldprofiles`
//                          WHERE `$field` IS NOT NULL";
//                    $DB->doQuery($query, "LOAD TABLE profiles: " . $DB->error());
//                }
//
//                $mig->dropTable('glpi_plugin_reports_oldprofiles');
//            }
//
//
//            // -- SINCE 0.85 --
//            //Add new rights in glpi_profilerights table
//            $profileRight = new ProfileRight();
//
//            foreach ($DB->request(['FROM' => 'glpi_plugin_reports_profiles']) as $data) {
//                $right['profiles_id']   = $data['profiles_id'];
//                $right['name']          = "plugin_reports_" . $data['report'];
//                $droit                  = $data['access'];
//                if ($droit == 'r') {
//                    $right['rights'] = 1;
//                    $profileRight->add($right);
//                }
//            }
//            $mig->dropTable('glpi_plugin_reports_profiles');
//        }
    }


    public static function uninstall(Migration $mig)
    {
//        global $DB;
//
//        $tables = ['glpi_plugin_reports_profiles',
//            'glpi_plugin_reports_oldprofiles',
//            'glpi_plugin_reports_doublons_backlist',
//            'glpi_plugin_reports_doublons_backlists'];
//
//        foreach ($tables as $table) {
//            $mig->dropTable($table);
//        }
//
//        //Delete rights associated with the plugin
//        $query = "DELETE
//                FROM `glpi_profilerights`
//                WHERE `name` LIKE 'plugin_reports_%'";
//        $DB->doQuery($query, $DB->error());
    }


    /**
     * @see inc/CommonGLPI::getTabNameForItem()
    **/
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        global $DB;

        if ($item->getType() == \Profile::class) {
            if ($item->getField('interface') == 'central') {
                $nb = 0;
                if (Session::haveRight('reports', READ)) {
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        $query = $DB->request(['COUNT' => 'cpt',
                            'FROM' => 'glpi_profilerights',
                            'WHERE' => ['profiles_id' => $_GET['id'],
                                'name'        => ['LIKE', 'plugin_reports_%'],
                                'rights'      => 1]]);
                        foreach ($query as $data_nb) {
                            $nb = $data_nb['cpt'];
                        }
                    }
                    return self::createTabEntry(Report::getTypeName($nb), $nb);
                }
            }
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {

        if ($item->getType() == \Profile::class) {
            if ($item->getField('interface') == 'central') {
                $ID = $item->getField('id');

                $prof = new self();
                $prof->updatePluginRights();

                self::showForProfile($item);
            }
        }
        return true;
    }
}
