#!/bin/bash

#
# -------------------------------------------------------------------------
#  LICENSE
#
# This file is part of Reports plugin for GLPI.
#
# Reports is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Reports is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with Reports. If not, see <http://www.gnu.org/licenses/>.
#
# @authors   Nelly Mahu-Lasson, Remi Collet, Alexandre Delaunay, Xavier Caillaud, Infotel
# @copyright Copyright (c) 2009-2026 Reports plugin team
# @license   AGPL License 3.0 or (at your option) any later version
# @link      https://github.com/InfotelGLPI/reports
# @link      http://www.glpi-project.org/
# @package   reports
# @since     2009
#            http://www.gnu.org/licenses/agpl-3.0-standalone.html
# --------------------------------------------------------------------------
#

soft='GLPI - Reports plugin'
version='0.84'
email=glpi-translation@gna.org
copyright='INDEPNET Development Team'

#xgettext *.php */*.php -copyright-holder='$copyright' --package-name=$soft --package-version=$version --msgid-bugs-address=$email -o locales/en_GB.po -L PHP --from-code=UTF-8 --force-po  -i --keyword=_n:1,2 --keyword=__ --keyword=_e

# Only strings with domain specified are extracted (use Xt args of keyword param to set number of args needed)

xgettext *.php */*.php  */*/*.php -o locales/glpi.pot -L PHP --add-comments=TRANS --from-code=UTF-8 --force-po  \
      --keyword=_n:1,2,4t --keyword=__s:1,2t --keyword=__:1,2t --keyword=_e:1,2t --keyword=_x:1c,2,3t --keyword=_ex:1c,2,3t \
      --keyword=_sx:1c,2,3t --keyword=_nx:1c,2,3,5t


### for using tx :
##tx set --execute --auto-local -r GLPI_example.glpi-084-current 'locales/<lang>.po' --source-lang en --source-file locales/glpi.pot
## tx push -s
## tx pull -a


