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

namespace GlpiPlugin\Reports;

use Dropdown;
use Glpi\DBAL\QueryExpression;

/**
 * Criteria which allows to select a time interval
 */
class TimeIntervalCriteria extends AutoCriteria {


   /**
    * @param $report
    * @param $name      (default time-interval)
    * @param $label     (default '')
   **/
   function __construct($report, $name='time-interval', $label='') {
      parent::__construct($report, $name, $name, $label);
   }


   public function setDefaultValues() {

      $this->setStartTime(date("Y-m-d"));
      $this->setEndTime(date("Y-m-d"));
   }


   function setStartTime($starttime) {
      $this->addParameter('starttime',$starttime);
   }


   function setEndtime($endtime) {
      $this->addParameter('endtime',$endtime);
   }


   function displayCriteria() {

      $this->getReport()->startColumn();

      printf(__('Start at %s'), __('Number pending', 'reports'));
      echo "&nbsp;&nbsp;";
      $this->getReport()->endColumn();

      $this->getReport()->startColumn();
      Dropdown::showHours("starttime", $this->getParameter('starttime'));
      $this->getReport()->endColumn();

      $this->getReport()->startColumn();
      printf(__('End at %s'), __('Number pending', 'reports'));
      echo "&nbsp;&nbsp;";
      $this->getReport()->endColumn();

      $this->getReport()->startColumn();
      Dropdown::showHours("endtime", $this->getParameter('endtime'));
      $this->getReport()->endColumn();
   }


   /**
    * Normalize a user-supplied time parameter into a safe "HH:MM:00" SQL literal.
    *
    * The value is posted by Dropdown::showHours() as "HH:MM", but it is user-controlled input
    * that ends up interpolated verbatim into a QueryExpression, so it must be strictly validated
    * before use. Falls back to "00:00:00" when the input does not match the expected format,
    * which prevents any SQL injection through starttime/endtime.
    *
    * @param string $param Parameter name ('starttime' or 'endtime')
    * @return string A safe "HH:MM:00" literal
    */
   private function getSafeTime($param) {

      $value = (string) $this->getParameter($param);
      if (preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) === 1) {
         return $value . ":00";
      }
      return "00:00:00";
   }


   /**
    * @see plugins/reports/inc/AutoCriteria::getSqlCriteriasRestriction()
   **/
   function getSqlCriteriasRestriction($link='AND') {

      $begin = $this->getSafeTime('starttime');
      $end   = $this->getSafeTime('endtime');

      if ($this->getParameter("starttime") < $this->getParameter("endtime")) {
         // ex  08:00:00 <= time < 18:00:00
         return " $link TIME(".$this->getSqlField().") >= '".$begin."'
                 AND TIME(" .$this->getSqlField(). ") < '" .$end."'";
      }
      // ex time < 08:00:00 or 18:00:00 <= time
      return " $link (TIME(". $this->getSqlField().") >= '".$begin."'
                      OR TIME(".$this->getSqlField().") < '".$end."')";
   }

    /**
     * Get SQL code associated with the criteria
     */
    public function getNewSqlCriteriasRestriction($link = 'AND') {

        $begin = $this->getSafeTime('starttime');
        $end   = $this->getSafeTime('endtime');

        return [
            new QueryExpression("TIME(" . $this->getSqlField() . ") >= '$begin'"),
            new QueryExpression("TIME(" . $this->getSqlField() . ") < '$end'"),
        ];
    }


   function getSubName() {

      $title = $this->getCriteriaLabel($this->getName());
      if (empty($title)) {
         if ($this->getName() == 'date-interval') {
            $title = __('Date interval', 'reports');
         } if ($this->getName() == 'time-interval') {
            $title = __('Time interval', 'reports');
         }
      }
      return sprintf(__('%1$s (%2$s)'), "&nbsp;" . $title,
                     $this->getParameter('starttime') . "," . $this->getParameter('endtime'));
   }

}
