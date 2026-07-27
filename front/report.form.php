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

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Reports\Profile;
use GlpiPlugin\Reports\Report;

Session::checkRight('profile', READ);

Html::header(
    __('Reports plugin configuration', 'reports'),
    $_SERVER['PHP_SELF'],
    'config',
    'plugins'
);

global $DB, $LANG;


$report = '';
if (isset($_POST['report'])) {
    $report = $_POST['report'];
}

$prof = new Profile();

if (isset($_POST['delete']) && $report) {
    // Broken access control: this branch mutates profile rights for every profile at once
    // (deleteByCriteria then re-add with default access), so it must require write access on
    // "profile" like the update branch — the page-level READ check is not enough.
    Session::checkRight('profile', UPDATE);
    $profile_right = new ProfileRight();
    $profile_right->deleteByCriteria(['name' => "plugin_reports_$report"]);
    ProfileRight::addProfileRights(["plugin_reports_$report"]);
} elseif (isset($_POST['update']) && $report) {
    Session::checkRight('profile', UPDATE);
    Profile::updateForReport($_POST);
}

$tab = $prof->updatePluginRights();

$reports_names = Report::getAllReportsTitles();

// Build the grouped option tree (plugin > section > options) consumed by the Twig template.
// Labels/values are user- or plugin-provided, so they are output through Twig auto-escaping.
$indent   = "\u{00a0}\u{00a0}\u{00a0}\u{00a0}\u{00a0}";
$plugname = [];
$rap      = [];
foreach ($tab as $key => $plug) {
    $mod = (($plug == 'reports') ? $key : $plug . '_' . $key);
    if (!isset($plugname[$plug])) {
        // Retrieve the plugin name
        $function        = "plugin_version_$plug";
        $tmp             = $function();
        $plugname[$plug] = $tmp['name'];
    }
    $section = (isStat($mod) ? sprintf(__('%1$s - %2$s'), __('Assistance'), __('Statistics'))
                            : sprintf(__('%1$s - %2$s'), __('Tools'), __('Report', 'Reports', 2)));

    if (isset($reports_names[$key])) {
        $rap[$plug][$section][$mod] = $reports_names[$key];
    } else {
        $rap[$plug][$section][$mod] = $LANG["plugin_$plug"][$key];
    }
}

$option_groups = [];
foreach ($rap as $plug => $sections) {
    $group = [
        'label'    => sprintf(__('%1$s - %2$s'), __('Plugins'), $plugname[$plug]),
        'sections' => [],
    ];
    foreach ($sections as $section => $options) {
        $section_data = [
            'label'   => $indent . "\u{00bb}\u{00a0}" . $section,
            'options' => [],
        ];
        foreach ($options as $mod => $name) {
            $section_data['options'][] = [
                'value'    => $mod,
                'label'    => $indent . $indent . $name,
                'selected' => ($report == "$mod"),
            ];
        }
        $group['sections'][] = $section_data;
    }
    $option_groups[] = $group;
}

TemplateRenderer::getInstance()->display('@reports/report_config.html.twig', [
    'action'        => $_SERVER['REQUEST_URI'],
    'title'         => __('Reports plugin configuration', 'reports'),
    'rights_title'  => __('Rights management by report', 'reports'),
    'report_label'  => __('Report', 'Reports', 1),
    'option_groups' => $option_groups,
]);

if ($report) {
    Profile::showForReport($report);
}

Html::footer();
