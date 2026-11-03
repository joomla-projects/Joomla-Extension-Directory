# Legacy VEL data: inventory (R-04), imports (M-01, M-02, M-03), removal (M-04)

## What this repository knows

| Source | Where | State |
|---|---|---|
| RSForms submissions: forms 5, 10, 11 (vulnerability reports across three generations of the form), 7, 12 (developer updates) | staged by `plg_sampledata_jed_migrate/sql/rsforms.sql` as `old_rsformN` | **imported by `vel_reports.sql`** (M-02): each becomes a `#__vel_reports` row of kind `vulnerability` or `fix_notice`, state `triaged`, channel `legacy`, keyed by `(legacy_form_id, legacy_submission_id)`; the reporter's address is kept only where consent was recorded; the extension is resolved to a listing where the name matches exactly one |
| RSTicketsPro department 5 (VEL): 218 tickets, 627 messages | JED3 database; excluded by `tickets.sql` | **open (M-03)**: PRD recommends importing into `#__vel_messages`/`#__vel_participants`; needs the decision and the JED3 database |
| The published VEL entries (the current `/vulnerable-extensions/` pages) | JED3 database - believed to be `com_content` articles in Live / Resolved / Unresolved categories with structured intro text | **open (R-04 then M-01)** |
| `#__jed_vel_*` tables in this repository | never fed in production | dropped in M-04 |

## R-04 checklist

- [ ] Locate the published entries in the JED3 database: table, category ids, the intro-text
      structure ("Risk level", "Vulnerable versions", "Patched version", "Exploit type",
      "Recommendation", "Update notice", "Discovered by", "Coordinated by", "CVE"...). Sample
      twenty rows across the three categories into `docs/vel/migration/samples/`.
- [ ] Capture `https://extensions.joomla.org/vulnerable-extensions/json-feed/` verbatim into
      `tests/fixtures/vel/legacy-feed.json` and list its field names; adjust
      `components/com_vel/src/View/Legacyfeed/JsonView.php` to match byte for byte.
- [ ] List the known consumers of the legacy feed (checker extensions, services) and set the
      sunset date with them in mind (D-8: twelve months).
- [ ] Record the legacy URL patterns per category for the redirect map.
- [ ] Count RSTicketsPro department-5 rows and decide M-03.

## M-01 plan (after R-04)

A new `plg_sampledata_jed_migrate` step, `vel_advisories.sql` (or PHP where parsing is needed):

1. One advisory per legacy entry: `source = legacy`, `state = published`, `status` from the
   category (Live → live, Resolved → patched, Unresolved → abandoned), `title` with legacy prefixes
   stripped, `description` from the article text, `alias` from the legacy alias (kept, so old
   URLs can redirect).
2. One product from the extension name; `version_from = *`, `version_to` from "Vulnerable
   versions"; `fixed_version` from "Patched version"; the listing resolved by exact, unambiguous
   name as `vel_reports.sql` does.
3. CWE from "Exploit type" (Appendix A map); severity from "Risk level" with
   `severity_reason = 'legacy risk level'`; CVE id when present; a reference from "Update notice".
4. Credits from "Discovered by" (finder), "Coordinated by" (coordinator).
5. `date_published`/`date_updated` through the "Correct dates" path (direct UPDATE in the step,
   since the service would stamp now).
6. A per-row parse report written to the migration log; rows that cannot be parsed land as
   `draft` with the raw text in `internal_notes`.
7. `#__redirect_links` rows from each legacy URL to `/vulnerable-extensions/<id>-<alias>`.

## M-04 checklist — removing the legacy VEL code from com_jed (after go-live)

Coordinate with the JED team; this touches their component.

- [ ] Confirm `#__jed_vel_report`, `#__jed_vel_developer_update`, `#__jed_vel_abandoned_report`,
      `#__jed_vel_vulnerable_item` are empty in production.
- [ ] Delete in `administrator/components/com_jed`: `src/Controller/Vel*`, `src/Model/Vel*`,
      `src/Table/Vel*`, `src/View/Vel*`, `forms/vel*.xml`, `forms/filter_vel*.xml`, `tmpl/vel*`,
      `layouts/vulnerableitem/`, `layouts/ticket/linked_vel*.php`, `layouts/ticket/velreport.php`.
- [ ] Delete in `components/com_jed`: `src/Controller/Vel*`, `src/Model/Vel*`, `src/View/Vel*`,
      `forms/vel*.xml`, `tmpl/vel*`.
- [ ] Delete `media/com_jed/css/jedvel.css`.
- [ ] Remove the `vulnerable` toolbar button in `JedHelper::addConfigToolbar()`.
- [ ] Remove `COM_JED_VEL_*` strings from both language files.
- [ ] Remove seed rows 4–6 from `#__jed_ticket_linked_item_types` in `install.mysql.utf8.sql` and
      add a `sql/updates/mysql/<version>.sql` that deletes them and drops the four tables;
      remove the four `CREATE TABLE` blocks from the install file and the drops from
      `reset.mysql.utf8.sql`.
- [ ] Update the comments in `TicketType` that still describe 4–6 as "removed along with com_vel"
      to point at `Vulnerability = 11` as the replacement.
