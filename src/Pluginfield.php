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

use CommonDBTM;
use Session;

class Pluginfield extends CommonDBTM
{
    /**
     * Return the localized name of the current Type
     * Shoudl be overloaded in each new class
     *
     * @param $nb  integer  for singular / plural
     *
     * @return string
     */

    public static $rightname = "config";

    public static function getTypeName($nb = 0)
    {
        return _n('Report', 'Reports', $nb);
    }

    public static function canCreate(): bool
    {
        return Session::haveRightsOr(self::$rightname, [UPDATE]);
    }

    public static function canView(): bool
    {
        if (static::$rightname) {
            return Session::haveRight(static::$rightname, READ);
        }
        return false;
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canUpdateItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public static function canDelete(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canDeleteItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public static function canPurge(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function canPurgeItem(): bool
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }
    public function canCreateItem(): bool
    {

        return Session::haveRight(self::$rightname, UPDATE);
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
}
