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

use Glpi\Application\View\TemplateRenderer;

$USEDBREPLICATE         = 1;
$DBCONNECTION_REQUIRED  = 0;

global $DB;

Session::checkRight("plugin_reports_rules", READ);

/**
 * Table of the rules of a collection, as variables of the table template
 */
function plugin_reports_rulelist($rulecollection, $title): array
{

    Session::checkRight($rulecollection::$rightname, READ);

    $rulecollection->getCollectionDatas(true, true);

    $table = [
        'class'       => 'tab_cadre',
        'header_rows' => [
            ['cells' => [[
                //TRANS: The name of the report = Rule's catalog
                'value'   => sprintf(__('%1$s - %2$s'), __("Rule's catalog", 'reports'), $title),
                'href'    => $_SERVER["REQUEST_URI"],
                'colspan' => 6,
            ]]],
            ['cells' => [
                ['value' => __('Name')],
                ['value' => __('Description')],
                ['value' => _n('Criterion', 'Criteria', 2), 'colspan' => 2],
                ['value' => _n('Action', 'Actions', 2)],
                ['value' => __('Active')],
            ]],
        ],
        'rows'        => [],
    ];

    foreach ($rulecollection->RuleList->list as $rule) {
        $criteria_lines = [];
        foreach ($rule->criterias as $criteria) {
            $criteria_lines[] = $rule->getCriteriaName($criteria->fields["criteria"]) . " "
                . RuleCriteria::getConditionByID($criteria->fields["condition"], get_class($rule)) . " "
                . $rule->getCriteriaDisplayPattern(
                    $criteria->fields["criteria"],
                    $criteria->fields["condition"],
                    $criteria->fields["pattern"],
                );
        }

        $action_lines = [];
        foreach ($rule->actions as $action) {
            $action_lines[] = $rule->getActionName($action->fields["field"]) . " "
                . RuleAction::getActionByID($action->fields["action_type"]) . " "
                . stripslashes((string) $rule->getActionValue(
                    $action->fields["field"],
                    $action->fields["action_type"],
                    $action->fields["value"],
                ));
        }

        $table['rows'][] = [
            'class' => 'tab_bg_1',
            'cells' => [
                ['value' => $rule->fields["name"]],
                ['value' => $rule->fields["description"]],
                ['value' => $rule->fields["match"] == Rule::AND_MATCHING ? __('and') : __('or')],
                ['lines' => $criteria_lines],
                ['lines' => $action_lines],
                ['value' => $rule->fields["is_active"] ? __('Yes') : __('No')],
            ],
        ];
    }

    return $table;
}
Html::header(__("Rule's catalog", 'reports'), '', "utils", "report");

Report::title();

$allowed_types = ['ldap', 'soft', ''];
$type = (isset($_GET["type"]) && in_array($_GET["type"], $allowed_types, true)) ? $_GET["type"] : "";

if ($type == "ldap") {
    $table = plugin_reports_rulelist(new RuleRightCollection(), __('Authorizations assignment rules'));

} elseif ($type == "soft") {
    $table = plugin_reports_rulelist(new RuleSoftwareCategoryCollection(), __('Rules for assigning a category to software'));

} else {
    // REQUEST_URI already carries a query string whenever the page was reached with one, so a
    // hard-coded "?" produced a second separator and the type parameter was simply ignored.
    $self_url   = $_SERVER["REQUEST_URI"];
    $separator  = str_contains($self_url, '?') ? '&' : '?';

    $table = [
        'class'       => 'tab_cadre',
        'header_rows' => [['cells' => [[
            'value' => sprintf(__('%1$s - %2$s'), __("Rule's catalog", 'reports'), __('Rule type')),
        ]]]],
        'rows'        => [],
    ];

    if (Session::haveRight("rule_ldap", READ)) {
        $table['rows'][] = ['class' => 'tab_bg_1', 'cells' => [[
            'value' => __('Authorizations assignment rules'),
            'href'  => $self_url . $separator . 'type=ldap',
            'class' => 'center b',
        ]]];
    }

    if (Session::haveRight("rule_softwarecategories", READ)) {
        $table['rows'][] = ['class' => 'tab_bg_1', 'cells' => [[
            'value' => __('Rules for assigning a category to software'),
            'href'  => $self_url . $separator . 'type=soft',
            'class' => 'center b',
        ]]];
    }
}

TemplateRenderer::getInstance()->display('@reports/report/page.html.twig', ['tables' => [$table]]);
Html::footer();
