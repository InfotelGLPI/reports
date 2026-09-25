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

use CommonGLPI;
use Glpi\Application\View\TemplateRenderer;
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

        if (!$prof instanceof \Profile || !self::canView()) {
            return false;
        }

        $profile = new \Profile();
        $profile->getFromDB($prof->getID());

        $rights = self::getAllRights();

        $twig = TemplateRenderer::getInstance();
        $twig->display('@reports/profile.html.twig', [
            'id' => $prof->getID(),
            'profile' => $profile,
            'title' => self::getTypeName(Session::getPluralNumber()),
            'rights' => $rights,
        ]);
    }


    /**
     * @param $report
    **/
    public static function showForReport($report)
    {
        /* call from front/config.form.php
        * $report = "bar" (from reports) or "foo_bar" (other plugins)
         */
        if (empty($report) || !Session::haveRight('profile', READ)) {
            return false;
        }
        $current = self::getAllProfilesRights(['name' => 'plugin_reports_' . $report]);
        $canedit = Session::haveRight('profile', UPDATE);

        $profiles = [];
        foreach (self::getReportProfiles($report) as $data) {
            $canaccess = $data['canaccess'];

            // Capture the GLPI right dropdown (or the hidden "no access" field) as already-safe
            // HTML so the Twig template can output it via |raw while auto-escaping the rest.
            ob_start();
            if ($canaccess && !$data['editable']) {
                // Profile above the current one: shown, but not offered for edition
                // (updateForReport() would ignore it anyway).
                echo htmlescape(($current[$data['id']] ?? 0) ? __('Read') : __('No access'));
            } elseif ($canaccess) {
                \Profile::dropdownRight(
                    (string) $data['id'], // the input is named after the profile id
                    ['value'   => ($current[$data['id']] ?? 0),
                        'nonone'  => 0,
                        'noread'  => 0,
                        'nowrite' => 1],
                );
            } else {
                // Can't access because missing right from GLPI core
                echo Html::hidden((string) $data['id'], ['value' => 'NULL']);
            }
            $field = ob_get_clean();

            $profiles[] = [
                'name'      => $data['name'],
                'canaccess' => $canaccess,
                'field'     => $field,
            ];
        }

        TemplateRenderer::getInstance()->display('@reports/report_rights.html.twig', [
            'canedit'   => $canedit,
            'report'    => $report,
            // Use REQUEST_URI (not PHP_SELF): under the GLPI 11 front controller PHP_SELF resolves to
            // the router entry point, so posting the form there misroutes it. The value is output
            // through Twig auto-escaping ({{ action }}), which neutralizes the reflected-XSS vector.
            'action'    => $_SERVER['REQUEST_URI'],
            'header'    => __('Profiles rights', 'reports'),
            'no_access' => __('No access'),
            'footnote'  => isStat($report)
                ? __('No right on Assistance / Statistics', 'reports')
                : __('No right on Tools / Reports', 'reports'),
            'profiles'  => $profiles,
        ]);
    }

    /**
     * @param $input
    **/
    /**
     * Whitelist of valid report tokens (the "<report>" part of the
     * "plugin_reports_<report>" right names). Derived from the reports actually
     * registered by active plugins, mirroring the tokens built in
     * front/report.form.php and self::getAllRights().
     *
     * @return array<string,string> valid tokens keyed by themselves
     */
    public static function getValidReportTokens()
    {
        $tokens = [];
        foreach (Report::searchReport() as $key => $plug) {
            $token          = ($plug === 'reports') ? $key : $plug . '_' . $key;
            $tokens[$token] = $token;
        }
        return $tokens;
    }


    /**
     * Tells whether a posted report token maps to a real registered report.
     *
     * @param mixed $report
     *
     * @return bool
     */
    public static function isValidReport($report)
    {
        return is_string($report) && isset(self::getValidReportTokens()[$report]);
    }


    /**
     * Profiles listed on the rights page of a report, with the eligibility flags shared by the
     * display and the write path, so that a forged POST cannot reach a profile the form does
     * not offer.
     *
     * - canaccess: the profile holds the core right the report depends on (statistic/reports);
     * - editable: the profile is under the active one (Profile::currentUserHaveMoreRightThan()),
     *   so that the profile UPDATE right cannot be used to grant reports to a more privileged
     *   profile, or to one's own.
     *
     * @param string $report
     *
     * @return array<int, array{id: int, name: string, canaccess: bool, editable: bool}>
     */
    private static function getReportProfiles($report)
    {
        global $DB;

        $profiles = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM'   => 'glpi_profiles',
            'ORDER'  => 'name',
        ]) as $data) {
            $id         = (int) $data['id'];
            $profrights = ProfileRight::getProfileRights($id, ['statistic', 'reports']);
            $canstat    = (isset($profrights['statistic']) && $profrights['statistic']);
            $canreport  = (isset($profrights['reports'])   && $profrights['reports']);

            $profiles[$id] = [
                'id'        => $id,
                'name'      => $data['name'],
                'canaccess' => (isStat($report) && $canstat) || (!isStat($report) && $canreport),
                // The active profile always passes the hierarchy check (equal rights): only
                // a profile creator, who may edit any profile in the core, may change it here.
                'editable'  => \Profile::currentUserHaveMoreRightThan([$id])
                    && ($id !== (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0) || \Profile::canCreate()),
            ];
        }

        return $profiles;
    }


    /**
     * @param $input
    **/
    public static function updateForReport($input)
    {

        /* call from front/config.form.php
        * $report = "bar" (from reports) or "foo_bar" (other plugins)
         */
        $report = $input['report'] ?? '';
        // $report is interpolated into the "plugin_reports_<report>" right name and
        // persisted in glpi_profilerights; reject any value that does not map to a
        // real registered report so a forged POST cannot create arbitrary
        // plugin_reports_* rows.
        if (!self::isValidReport($report)) {
            return;
        }
        $prof      = new ProfileRight();
        $rightname = "plugin_reports_$report";
        $current   = self::getAllProfilesRights(['name' => $rightname], true);

        // The form only ever offers the rights Report::getRights() declares (READ), but the
        // posted value was written to glpi_profilerights verbatim: a forged POST persisted an
        // arbitrary bitmask under a plugin_reports_* right name, which every later
        // haveRight()/haveRightsOr() call on that name would then honour. Mask the value down
        // to the bits the report actually defines.
        $mask = 0;
        foreach (array_keys((new Report())->getRights()) as $right_bit) {
            $mask |= (int) $right_bit;
        }

        $profiles = self::getReportProfiles($report);

        foreach ($input as $profiles_id => $right) {
            if ($right == 'NULL') {
                $right = 0;
            }
            $right = (int) $right & $mask;
            if (is_numeric($profiles_id)) {
                // Only write for the profiles the form offers: existing, holding the core right
                // the report depends on, and under the active profile. Anything else (unknown
                // id, profile without access, more privileged or own profile) is ignored.
                $profile = $profiles[(int) $profiles_id] ?? null;
                if ($profile === null || !$profile['canaccess'] || !$profile['editable']) {
                    continue;
                }
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

        // $reports only carries the reports of the activated plugins, which is what the
        // configuration form displays. The reconciliation below needs more than that: it used to
        // list every glpi_profilerights row matching 'plugin_reports_%' and delete, for all
        // profiles at once, each name missing from that list. A right declared by another plugin
        // as plugin_reports_<plugin>_<report> matches the pattern but leaves the keep list as
        // soon as its provider is deactivated, so it was destroyed -- silently, and with the
        // value configured for every profile. This is the defect already corrected in
        // plugin_reports_uninstall(); enumerate here the reports of every plugin present on the
        // instance, whatever its state, so that only a report existing nowhere is purged.
        $rights = [];
        foreach ($reports + Report::searchReport(true) as $report => $plug) {
            if ($plug == 'reports') {
                $rights["plugin_reports_$report"] = 1;
            } else {
                $rights["plugin_reports_{$plug}_{$report}"] = 1;
            }
        }

        $obsolete_rights = [];
        foreach ($DB->request(['SELECT'   => 'name',
            'DISTINCT' => true,
            'FROM' => 'glpi_profilerights',
            'WHERE'    => ['name' => ['LIKE', 'plugin_reports_%']]]) as $data) {
            if (!isset($rights[$data['name']])) {
                $obsolete_rights[] = $data['name'];
            }
        }

        // Delete the leftovers of the reports that no longer exist, in a single statement bound
        // to the computed set of names rather than one statement per name.
        if (count($obsolete_rights) > 0) {
            $profile_right->deleteByCriteria(['name' => $obsolete_rights]);
        }
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

        if ($item instanceof \Profile) {
            if ($item->getField('interface') == 'central') {
                $nb = 0;
                if (Session::haveRight('reports', READ)) {
                    if ($_SESSION['glpishow_count_on_tabs']) {
                        // $item is the profile being rendered; reading $_GET['id'] made the
                        // counter describe whatever identifier happened to be in the URL - a
                        // different profile on a nested tab render, and an undefined key notice
                        // when the tab was reached without it.
                        $query = $DB->request(['COUNT' => 'cpt',
                            'FROM' => 'glpi_profilerights',
                            'WHERE' => ['profiles_id' => $item->getID(),
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

        if ($item instanceof \Profile) {
            if ($item->getField('interface') == 'central') {
                // updatePluginRights() reconciles glpi_profilerights and runs deleteByCriteria()
                // on every plugin_reports_% row whose report is no longer registered: that is a
                // destructive write, and it was executed while merely rendering a tab. A tab is
                // loaded through ajax/common.tabs.php, which only tests READ on the carrying
                // item and is a GET - the request GLPI does not protect with the CSRF token - so
                // a profile holding nothing but "profile" READ wiped the rights of a plugin that
                // happened to be disabled at that moment, for every profile of the instance.
                // front/report.form.php already guards the very same call this way; the tab is
                // the parallel path where the guard was never replayed. Rendering is now free of
                // side effects.
                if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET'
                    && Session::haveRight('profile', UPDATE)) {
                    (new self())->updatePluginRights();
                }

                self::showForProfile($item);
            }
        }
        return true;
    }
}
