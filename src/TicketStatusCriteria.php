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

use Ticket;

/**
 * Ticket status selection criteria
**/
class TicketStatusCriteria extends ArrayCriteria
{
    private $choice = [];


    /**
     * @param $report
     * @param $name      (default 'status')
     * @param $label     (default '')
     * @param $option    (default 1)
    **/
    public function __construct($report, $name = 'status', $label = '', $option = 1)
    {

        if (is_array($option)) {
            foreach ($option as $opt) {
                $tab[$opt] = Ticket::getStatus($opt);
            }

        } elseif ($option == 1) {
            $tab = Ticket::getAllStatusArray(true);

        } else {
            $tab = Ticket::getAllStatusArray(false);
        }

        // Parent is ArrayCriteria
        parent::__construct($report, $name, ($label ? $label : _n('Status', 'Statuses', 1)), $tab);
    }


    /**
     * Get SQL code associated with the criteria
     *
     * @see plugins/reports/inc/ArrayCriteria::getSqlCriteriasRestriction()
    **/
    public function getSqlCriteriasRestriction($link = 'AND')
    {

        // Force a scalar context so an array-typed value (param[]=x) collapses to a
        // string and matches the "all"/default branch cleanly instead of raising a
        // PHP error; numeric status constants still match via the loose switch compare.
        $status = (string) $this->getParameterValue();

        switch ($status) {
            case "notold":
                $list = Ticket::getAllStatusArray();
                $check = array_merge(
                    Ticket::getSolvedStatusArray(),
                    Ticket::getClosedStatusArray(),
                );
                foreach ($check as $status) {
                    if (isset($list[$status])) {
                        unset($list[$status]);
                    }
                }
                $list = implode("','", array_keys($list));
                break;

            case "old":
                $list = implode("','", array_merge(
                    Ticket::getSolvedStatusArray(),
                    Ticket::getClosedStatusArray(),
                ));
                break;

            case "process":
                $list = implode("','", Ticket::getProcessStatusArray());
                break;

            case 'notclosed':
                $list = Ticket::getAllStatusArray();
                foreach (Ticket::getClosedStatusArray() as $status) {
                    if (isset($list[$status])) {
                        unset($list[$status]);
                    }
                }
                $list = implode("','", array_keys($list));
                break;

            case Ticket::INCOMING:
            case Ticket::ASSIGNED:
            case Ticket::PLANNED:
            case Ticket::WAITING:
            case Ticket::SOLVED:
            case Ticket::CLOSED:
                $list = $status;
                break;

            case "all":
            default:
                return '';
        }
        return $link . " " . $this->getSqlField() . " IN ('" . $list . "') ";
    }

    /**
     * Get SQL code associated with the criteria
     *
     * @see plugins/reports/inc/ArrayCriteria::getSqlCriteriasRestriction()
     **/
    public function getNewSqlCriteriasRestriction($link = 'AND')
    {

        $status = $this->getParameterValue();

        switch ($status) {
            case "notold":
                $list = Ticket::getAllStatusArray();
                $check = array_merge(
                    Ticket::getSolvedStatusArray(),
                    Ticket::getClosedStatusArray(),
                );
                foreach ($check as $status) {
                    if (isset($list[$status])) {
                        unset($list[$status]);
                    }
                }
                $list = array_keys($list);
                break;

            case "old":
                $list = array_merge(
                    Ticket::getSolvedStatusArray(),
                    Ticket::getClosedStatusArray(),
                );
                break;

            case "process":
                $list = Ticket::getProcessStatusArray();
                break;

            case 'notclosed':
                $list = Ticket::getAllStatusArray();
                foreach (Ticket::getClosedStatusArray() as $status) {
                    if (isset($list[$status])) {
                        unset($list[$status]);
                    }
                }
                $list = array_keys($list);
                break;

            case Ticket::INCOMING:
            case Ticket::ASSIGNED:
            case Ticket::PLANNED:
            case Ticket::WAITING:
            case Ticket::SOLVED:
            case Ticket::CLOSED:
                $list = [$status];
                break;

            case "all":
            default:
                return '';
        }

        return [$this->getSqlField() => $list];
    }

}
