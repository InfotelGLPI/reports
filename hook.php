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

function plugin_reports_install()
{
    global $DB;

    // The table backing GlpiPlugin\Reports\Pluginfield was never created, so the statdeployment
    // report and the additional fields configuration screen both hit a missing table since the
    // very first install. Migration::addTable() does not exist, a plain CREATE TABLE is the idiom.
    $table = 'glpi_plugin_reports_pluginfields';
    if (!$DB->tableExists($table)) {
        $default_charset   = DBConnection::getDefaultCharset();
        $default_collation = DBConnection::getDefaultCollation();
        $default_key_sign  = DBConnection::getDefaultPrimaryKeySignOption();

        $DB->doQuery(
            "CREATE TABLE IF NOT EXISTS `$table` (
                `id` INT {$default_key_sign} NOT NULL AUTO_INCREMENT,
                `report` VARCHAR(255) DEFAULT NULL,
                `itilcategories_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `glpi_plugin_fields_containers_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                `glpi_plugin_fields_fields_id` INT {$default_key_sign} NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `report` (`report`),
                KEY `itilcategories_id` (`itilcategories_id`)
            ) ENGINE = InnoDB DEFAULT CHARSET = {$default_charset} COLLATE = {$default_collation} ROW_FORMAT = DYNAMIC;",
        );
    }

    return true;
}


function plugin_reports_uninstall()
{
    // Remove every profile right the plugin dynamically created (name LIKE 'plugin_reports_%'),
    // otherwise these lines stay orphaned in glpi_profilerights after uninstall.
    (new ProfileRight())->deleteByCriteria(['name' => ['LIKE', 'plugin_reports_%']]);

    // Drop the plugin table, plus the legacy ones inherited from older versions if they still
    // exist.
    $migration = new Migration(PLUGIN_REPORTS_VERSION);
    foreach ([
        'glpi_plugin_reports_pluginfields',
        'glpi_plugin_reports_profiles',
        'glpi_plugin_reports_oldprofiles',
    ] as $table) {
        $migration->dropTable($table);
    }
    $migration->executeMigration();

    return true;
}
