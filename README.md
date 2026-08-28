# Reports plugin for GLPI

Additional ready-to-use reports for GLPI, plus a lightweight framework to build and
plug in your own reports with minimal code.

![Reports](report_page.png)

## Description

The **Reports** plugin adds a *Reports* entry under the **Tools** menu that gathers a set
of predefined reports (inventory, financial, helpdesk statistics, etc.). Each report is a
self-contained PHP script discovered automatically, guarded by its own GLPI right, so
access can be granted per report and per profile.

It also ships an `AutoReport` builder (declarative *criterias* + typed *columns*) that lets
you assemble a filterable, paginated, exportable report without rewriting the HTML/SQL
plumbing.

## Compatibility

| | |
|---|---|
| GLPI | `>= 11.0.0` and `< 12.0.0` |
| PHP | `>= 8.2` |
| License | AGPLv3+ |

## Features

- A catalog of predefined reports, each shown in the *Tools > Reports* list.
- **Per-report rights**: every report owns a `plugin_reports_<key>` right, editable from the
  usual *Administration > Profiles* tab. A user only sees the reports their profile allows.
- **Pluggable**: a report is just a directory under `report/` — no core change needed to add
  one (see [Adding a new report](#adding-a-new-report)).
- **`AutoReport` framework**: reusable *criteria* widgets (dates, dropdowns, groups, users,
  ticket status/type/category, software, suppliers…) and typed *column* renderers (link,
  date, integer, float, map, model/type…) for consistent, export-ready output.

## Bundled reports

Inventory & assets:

- Applications by locations and versions
- Duplicate computers
- List all devices of a group, ordered by users
- Number of equipments by location
- List of equipments by location
- Number of items by entity
- Printers
- Location tree
- List of transfered objects
- Time before equipment start-up

Software & licenses:

- History of last software's installations
- Software version installations
- Detailed report of software installation by status
- Detailed license report
- Licenses by expiration date

Financial:

- Financial information
- Search in the financial information

Users, groups & rights:

- List of groups and members
- Users with no right
- Rule's catalog

Hardware history:

- History of last hardware's installations
- Global History (test / example only)

Helpdesk statistics:

- Helpdesk requesters and tickets by entity
- Tickets opened at night, sorted by priority
- Tickets not closed, sorted by priority
- Tasks list per user

> A few deployment/customer-specific statistics reports (e.g. `statdeployment`,
> `statticketsrennesmetropole*`) are also present as examples.

## Installation

1. Copy this directory into the GLPI `plugins/` (or `marketplace/`) folder under the name
   `reports`.
2. Go to **Setup > Plugins**, then **Install** and **Enable** *Reports*.
3. Grant the reports you need in **Administration > Profiles > Reports**.

Reports are then available under **Tools > Reports**.

## Rights

Rights are dynamic: one `plugin_reports_<key>` right is created per report. On install, the
current (super-admin) profile is granted access via `Profile::createFirstAccess()`. On
uninstall, every `plugin_reports_%` right is removed from `glpi_profilerights` and the plugin
tables are dropped, leaving no orphan rows.

## Adding a new report

1. Create a directory `report/<mykey>/` containing a `report/<mykey>/<mykey>.php` script.
2. At the top of the script, guard it with the report right and the relevant object right:

   ```php
   Session::checkRight("plugin_reports_<mykey>", READ);
   ```

3. Add the report title translation key `<mykey>_report_title` in the plugin locales (or a
   fallback entry in `Report::getReportsTitles()`).
4. Build the output — either with plain GLPI helpers/`TemplateRenderer`, or by instantiating
   `GlpiPlugin\Reports\AutoReport` and adding criterias/columns.

The report is picked up automatically (`glob(report/*)`), listed under *Tools > Reports*, and
its right becomes editable in the Profiles tab — no core file to edit.

## Authors

Infotel — Xavier Caillaud, Nelly Mahu-Lasson, Rémi Collet, and contributors.

## Links

- Homepage: <https://github.com/InfotelGLPI/reports>
- GLPI: <https://glpi-project.org>

## License

Distributed under the terms of the **GNU AGPL v3.0 or later**. See [LICENSE](LICENSE).
