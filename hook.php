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


    // Seed the profile rights. Without this, no plugin_reports_* line exists in
    // glpi_profilerights after a fresh install: Session::haveRight() is false for every
    // report, setup.php registers no menu entry and all the report scripts answer with a
    // denial, even for a super administrator. Report::searchReport() cannot be used here
    // because it only lists ACTIVATED plugins and this hook runs before activation, so
    // enumerate the reports shipped by this plugin directly.
    $rights = [];
    foreach (glob(Plugin::getPhpDir('reports') . '/report/*', GLOB_ONLYDIR) as $path) {
        $rights['plugin_reports_' . basename($path)] = READ;
    }
    if (count($rights) > 0) {
        plugin_reports_addMissingProfileRights(array_keys($rights));
        foreach (Profile::getSuperAdminProfilesId() as $profiles_id) {
            ProfileRight::updateProfileRights($profiles_id, $rights);
        }
    }

    return true;
}


/**
 * Create the glpi_profilerights rows that are missing for the given right names.
 *
 * ProfileRight::addProfileRights() inserts one row per profile without ever looking at what
 * is already stored, and glpi_profilerights carries a unique key on (profiles_id, name). As
 * soon as a single pair is already there — a re-install, an install replayed after a failure,
 * or a plugin left in the "to be cleaned" state — the insert raises a 1062 that GLPI 11 turns
 * into an uncaught RuntimeException: plugin_reports_install() aborts halfway and the plugin
 * can no longer be installed without a manual clean-up of the table. Filling the gaps pair by
 * pair makes the seed replayable, and repairs a partial seed as well (a profile created after
 * the first install carries no row for these names).
 *
 * @param array<string> $names
 *
 * @return void
 */
function plugin_reports_addMissingProfileRights(array $names)
{
    global $DB;

    if (count($names) === 0) {
        return;
    }

    $existing = [];
    $iterator = $DB->request([
        'SELECT' => ['profiles_id', 'name'],
        'FROM'   => ProfileRight::getTable(),
        'WHERE'  => ['name' => $names],
    ]);
    foreach ($iterator as $row) {
        $existing[(int) $row['profiles_id']][$row['name']] = true;
    }

    $profiles = $DB->request([
        'SELECT' => ['id'],
        'FROM'   => Profile::getTable(),
    ]);
    foreach ($profiles as $profile) {
        $profiles_id = (int) $profile['id'];
        foreach ($names as $name) {
            if (isset($existing[$profiles_id][$name])) {
                continue;
            }
            $DB->insert(ProfileRight::getTable(), [
                'profiles_id' => $profiles_id,
                'name'        => $name,
            ]);
        }
    }

    // addProfileRights() flushes this cache on every call: the list of possible rights is
    // built from the very rows that were just created.
    ProfileRight::cleanAllPossibleRights();
}


function plugin_reports_uninstall()
{
    // Remove the profile rights the plugin created, enumerating the reports it actually ships
    // exactly as the install seed does. The purge used to run on the pattern
    // 'plugin_reports_%', which also matches the rights of any other plugin whose key starts
    // with "reports" (plugin_reports_foo_*): uninstalling this plugin silently revoked them.
    $names = [];
    foreach (glob(Plugin::getPhpDir('reports') . '/report/*', GLOB_ONLYDIR) as $path) {
        $names[] = 'plugin_reports_' . basename($path);
    }
    if (count($names) > 0) {
        (new ProfileRight())->deleteByCriteria(['name' => $names]);
    }

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
