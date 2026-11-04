# VEL 2 — build status against the PRD work breakdown

Legend: **done** = written in this repository; **needs install** = written but only exercisable
on a Joomla 6 installation with a database; **open** = not started, with the reason.

The code in this repository was written without a PHP runtime available, so every "done" item
below has had its structure checked but has not been executed. The first task on a Joomla
installation is to install the package, run the unit tests, and walk the five journeys in Q-05.

## Phase 0 — decisions and research

| ID | Status | Notes |
|---|---|---|
| R-00 | done | D-1…D-10 approved; recorded in the PRD header |
| R-01 | open (needs internet) | `feeds/nvd.md` carries the checklist and what is assumed |
| R-02 | open (needs internet + CNA credentials) | `feeds/cve.md` carries the checklist; `schema/README.md` says where the schema goes |
| R-03 | partly | 150 CWEs seeded by the install SQL; *Refresh from MITRE* in the CWE list loads the full set |
| R-04 | open (needs the JED3 database) | `migration/vel-legacy.md` carries the checklist |
| R-05 | open (infrastructure) | options exist for every value |

## Phase 1 — foundation and editorial core

| ID | Status | Notes |
|---|---|---|
| S-01 | done | manifest, provider, component, ACL, options, language, menu, `pkg_jed.xml` |
| S-02 | done | 14 tables, mail templates, CWE seed, update path anchor |
| S-03 | done | 17 enums, tables, `VelService` with transitions and invariants; unit tests for the pure parts |
| S-04 | done | CVSS 3.1 scoring (tested against FIRST examples), 4.0 parsing, version parser/range/matcher (tested) |
| A-01 | needs install | advisories list with filters, search, batch state buttons |
| A-02 | needs install | advisory editor: nested subforms, CWE autocomplete, reference tags, CVSS, JED listing resolver |
| A-03 | needs install | ready/publish/retire/republish with validation messages, history revisions, revision JSON |
| A-04 | needs install | dashboard counters and overdue list |
| A-05 | needs install | `plg_actionlog_vel` |
| P-01 | needs install | router (incl. API rule), list with tabs/filters/sort/pagination, CSS |
| P-02 | needs install | detail page with schema.org, retired notice, downloads |
| P-03 | needs install | RSS/Atom honouring the filters |
| P-04 | needs install | JSON v2: index, advisories, advisories/{id}, dataset (cached), ETag/304, CORS |
| P-05 | needs install | `POST check` with the tested matcher; fixed-version ceiling |
| Q-01 | done (tests) / open (CI) | PHPUnit suite in `tests/`; no CI configuration exists in this repository to extend |

## Phase 2 — intake and communication

| ID | Status | Notes |
|---|---|---|
| I-01 | needs install | `ReportService`: consent, rate limits, JED report privilege, guest verification, purge |
| I-02 | needs install | report form with JED pre-fill, attachments, honeypot, captcha, verification mail |
| I-03 | needs install | fix-notice form |
| I-04 | needs install | reports queue and report view with create/attach/triage/duplicate/reject/spam |
| I-05 | needs install | `MessageService`, participants, hashed tokens, thread layout, reply with visibility, Message-ID threading |
| I-06 | needs install | vendor notification from listing owner/maintainers/developer address; dates set |
| I-07 | needs install | participant case page with reply, attachments, draft preview for vendors |
| I-08 | needs install | dashboard overdue indicators; `vel.housekeeping` |
| I-09 | needs install | `plg_privacy_vel` by account and by e-mail |
| I-10 | needs install + library | IMAP via `webklex/php-imap` (not vendored), webhook receiver with HMAC; threading tested |

## Phase 3 — CVE

| ID | Status | Notes |
|---|---|---|
| C-01 | done | builder tested against the brief's example; structural validator |
| C-02 | needs install | CVE tab: preview, download both shapes, validation display |
| C-03 | needs install + credentials | reserve / submit / fetch through `CveServicesClient` |
| C-04 | done | `cve-workflow.md` |

## Phase 4 — feeds, migration, legacy

| ID | Status | Notes |
|---|---|---|
| F-01 | needs install | runner, runs/items tables, review views, *Run now* |
| F-02 | needs install + internet | cvelistV5 delta polling, mapper (tested), classifier (tested), merger, auto-draft |
| F-03 | needs install + internet | NVD discovery and enrichment, mapper (tested), throttling |
| F-04 | done | `feeds/cve.md`, `feeds/nvd.md` |
| M-01 | open (needs R-04) | plan in `migration/vel-legacy.md` |
| M-02 | done | `vel_reports.sql` step in `plg_sampledata_jed_migrate` |
| M-03 | open (decision + R-04) | RSTicketsPro department 5 |
| P-06 | needs install (shape needs R-04) | legacy feed reconstructed from the legacy schema; headers and sunset in place |
| M-04 | open (after go-live) | checklist in `migration/vel-legacy.md` |

## Phase 5 — JED and abandonware integration, consumer

| ID | Status | Notes |
|---|---|---|
| J-01 | needs install | `TicketType::Vulnerability = 11`, handler, layout, VL1 reason and mail, ticket on publish |
| J-02 | needs install | `VelBadge` and the hook in the listing template |
| J-03 | open | shared extension identities in com_jed (separate enhancement; the VEL reads the table if it exists) |
| J-04 | needs install | abandonware status reconciliation in housekeeping; `setAbandonwareCase()`; no `CaseSource::VEL` added to com_abandonware yet |
| J-05 | needs install | `plg_system_velcheck` with dataset and check modes |
| J-06 | open | `mod_vel_latest`, `plg_finder_vel` — optional |

## Phase 6 — hardening

| ID | Status | Notes |
|---|---|---|
| Q-02 | partly | checklist written; review needs a running site |
| Q-03 | open | needs a site with data |
| Q-04 | open | needs a site |
| Q-05 | open | Cypress journeys need a site |
| Q-06 | partly | admin guide, API doc, CVE workflow written; public "how to report" text lives in the form's intro strings |
