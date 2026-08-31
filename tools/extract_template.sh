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
      --keyword=_sx:1c,2,3t --keyword=_nx:1c,2,3,5t \
      `# php-cs-fixer adds a trailing comma to every multiline call, and xgettext counts it as` \
      `# one extra argument, so the specs above stop matching and strings are silently dropped.` \
      `# These duplicates accept the same calls with that extra argument. Keep both lists in sync.` \
      --keyword=_n:1,2,5t --keyword=__s:1,3t --keyword=__:1,3t --keyword=_e:1,3t --keyword=_x:1c,2,4t --keyword=_ex:1c,2,4t \
      --keyword=_sx:1c,2,4t --keyword=_nx:1c,2,3,6t


### for using tx :
##tx set --execute --auto-local -r GLPI_example.glpi-084-current 'locales/<lang>.po' --source-lang en --source-file locales/glpi.pot
## tx push -s
## tx pull -a

# --- Étape 2 : Extraction des chaînes Twig ---

# Append locales from Twig templates
SCRIPT_DIR=$(dirname $0)
WORKING_DIR=$(readlink -f "$SCRIPT_DIR/..") # Script will be executed from "vendor/bin" directory
# Define translate function args
F_ARGS_N="1,2"
F_ARGS__S="1"
F_ARGS__="1"
F_ARGS_X="1c,2"
F_ARGS_SX="1c,2"
F_ARGS_NX="1c,2,3"
F_ARGS_SN="1,2"

# Vendored templates must not land in the plugin catalogue.
EXCLUDE_REGEX="${EXCLUDE_REGEX:-.*/(vendor|node_modules|public/lib)/.*}"

for file in $(cd "$WORKING_DIR" && find . -regextype posix-egrep -not -regex "$EXCLUDE_REGEX" -name "*.twig")
do
    # 1. Convert file content to replace "{{ function(.*) }}" by "<?php function(.*); ?>" and extract strings via std input
    # 2. Replace "standard input:line_no" by file location in po file comments
    cat $file | perl -0pe "s/\{\{(.*?)\}\}/<?php \1; ?>/gism" | xgettext - \
        -o locales/glpi.pot \
        -L PHP \
        --add-comments=TRANS \
        --from-code=UTF-8 \
        --force-po \
        --join-existing \
        --sort-output \
        --keyword=_n:$F_ARGS_N \
        --keyword=__:$F_ARGS__ \
        --keyword=_x:$F_ARGS_X \
        --keyword=_nx:$F_ARGS_NX \
        --keyword=__s:$F_ARGS__S \
        --keyword=_sx:$F_ARGS_SX \
        --keyword=_sn:$F_ARGS_SN
    sed -i -r "s|standard input:([0-9]+)|`echo $file | sed "s|./||"`:\1|g" locales/glpi.pot
done

# --- Report des nouvelles chaînes dans les traductions existantes ---
# xgettext ne met à jour que le .pot : sans cette étape, les chaînes déjà traduites
# restent marquées obsolètes (#~) dans les .po et ne sont plus compilées dans les .mo.
for po_file in locales/*.po; do
    [ -e "$po_file" ] || continue
    msgmerge --quiet --no-fuzzy-matching --backup=none --update "$po_file" locales/glpi.pot
done
