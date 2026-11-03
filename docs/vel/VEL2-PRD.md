# VEL 2 — Product Requirements Document

**Component:** `com_vel` (new, standalone, shipped inside `pkg_jed`)
**Target platform:** Joomla 6.x, PHP 8.3+, MySQL 8 / MariaDB 10.6+
**Status:** APPROVED (reviewer: all D-1…D-10 = YES, recommendations adopted; open questions in §10 default to the stated recommendation unless revisited)
**Source brief:** `VEL2.md`
**Build status:** see `docs/vel/STATUS.md` for what is implemented against the work breakdown in §9, and `docs/vel/README.md` for where everything lives.
**Author's note:** Every item marked **[DECISION]** needs a yes/no from the reviewer before the related
tasks start. Every item marked **[VERIFY]** is a fact about an external system taken from memory, to be
confirmed against the live service during the research task that owns it (R-01 … R-04).

---

## Table of contents

1. [Summary](#1-summary)
2. [Where we are today](#2-where-we-are-today)
3. [Architecture decision: standalone `com_vel`](#3-architecture-decision-standalone-com_vel)
4. [Scope, users and roles](#4-scope-users-and-roles)
5. [Decisions required before build](#5-decisions-required-before-build)
6. [Functional requirements](#6-functional-requirements)
   - 6.1 Data model
   - 6.2 Advisory lifecycle
   - 6.3 Backend administration
   - 6.4 Intake and communication
   - 6.5 Generate CVE JSON
   - 6.6 Inbound feeds (CNA, NVD)
   - 6.7 Public frontend
   - 6.8 Machine-readable outputs (RSS, JSON, check API)
   - 6.9 Integrations with `com_jed` and `com_abandonware`
   - 6.10 Migration and removal of the legacy VEL code
7. [Non-functional requirements](#7-non-functional-requirements)
8. [Technical design](#8-technical-design)
9. [Work breakdown](#9-work-breakdown)
10. [Open questions](#10-open-questions)
- Appendix A — Legacy → new field mapping
- Appendix B — Public JSON v2 and check-API contract
- Appendix C — Internal model → CVE Record 5.x mapping
- Appendix D — External API research notes (NVD, CVE Services, cvelistV5)
- Appendix E — Glossary

---

## 1. Summary

The Vulnerable Extension List is being rebuilt as a **first-class security advisory service** rather
than a list of articles. VEL 2 is a dedicated Joomla 6 component, `com_vel`, that:

- takes in vulnerability reports (web form, e-mail) and developer fix notices, and keeps the
  resulting correspondence in one place per case;
- stores each vulnerability as a structured **advisory** that can cover several products from one
  vendor, with per-product version ranges, CWE classifications, CVSS, references and credits, and
  the Joomla `type/element/folder/client_id` identity of each affected extension;
- drives a lean editorial workflow (`draft → ready → published → retired`) with automatically
  maintained reserved/published/updated dates;
- generates a **CVE Record 5.x JSON** draft from the stored data for the Joomla CNA, and later
  submits/reserves through CVE Services directly;
- pulls **inbound feeds** from the CVE Program (Joomla CNA records) and the NVD so that advisories
  published elsewhere reach the VEL without re-keying, and so that NVD's CVSS/CPE enrichment flows
  back into VEL records;
- publishes a filterable public list and detail pages at `/vulnerable-extensions/`, an RSS feed,
  a versioned JSON API, a legacy-compatible JSON feed, and a **check endpoint** a Joomla site can
  call with its installed extension identities to learn whether any are affected;
- integrates with `com_jed` (badge/block on an affected listing, JED-team ticket when the JED has
  to act) and `com_abandonware` (an advisory whose vendor never answers becomes an abandonware
  signal, and vice versa).

The brief's seven design principles (centralisation, efficiency, simplicity, consistency,
automation-friendliness, community value, scalability) are treated as acceptance criteria in §9.

---

## 2. Where we are today

### 2.1 What is in this repository

| Area | State | Notes |
|---|---|---|
| `com_jed` VEL code (`Vel*` controllers/models/views/tables, `velreport.xml`, `velvulnerableitem.xml`, `veldeveloperupdate.xml`, `velabandonedreport.xml`, 4 `#__jed_vel_*` tables, `layouts/vulnerableitem/*`, `media/com_jed/css/jedvel.css`) | **Legacy, partially dead** | A port of the JED3 data shapes. The site router (`components/com_jed/src/Service/Router.php`) registers none of the `vel*` views, so none of them has a SEF URL. The only live hook is a toolbar button in `JedHelper::addConfigToolbar()`. The design is one-advisory-one-product, stores CVSS 3.0 only, has no CWE list, no references, no credits, no Joomla identity fields, and no machine-readable output. |
| `TicketType` enum | Values 4/5/6 (the old `com_vel` report types) **deliberately removed** and reserved — "a ticket type that quietly means two things is worse than a gap in the numbering". `#__jed_ticket_linked_item_types` still seeds rows 4–6 (`Velreport`, `Veldeveloperupdate`, `Velabandonedreport`). | The PRD assigns the VEL a **new** value (11). |
| Project decisions referenced in code comments (`P0-04`, decision `8.13`) | "the new VEL is a separate component owned by the VEL team", "serves `/vulnerable-extensions/` from this same installation", "the JED's one obligation there is to stay out of the way" (`com_abandonware` Router, `jed2.php` sample data, `tickets.sql`). | VEL 2 honours these: it **is** that separate component. |
| `com_abandonware` | **The precedent** for a sibling component in `pkg_jed`: own tables, own ACL (`abandonware.contact/mark/resolve`), own config of policy numbers, a `CaseService` that is the only writer of state, reuse of `#__jed_tickets` via a `TicketType` + handler registered in `com_jed`, a report/case split ("a report is an event; a case is a process"). | VEL 2 follows the same structure and idioms throughout. |
| JED ticket engine (`#__jed_tickets`, `#__jed_ticket_messages`, `#__jed_ticket_internal_notes`, `TicketTypeHandlerRegistry`, `TicketHandlingTrait`, `mod_jed_opentickets`, `plg_privacy_tickets`) | Working, registered-users-only, **no access filtering by ticket type or group** (`TicketsModel` shows every ticket to every ticket manager), no inbound e-mail, outbound only via `MailTemplate`. | Relevant to Decision D-2. |
| Scheduling | `plg_task_jed` offers six routines (`jed.updatecheck`, `jed.queueworker`, `jed.linkcheck`, `jed.hitaggregate`, `jed.privacyprune`, `jed.abandonware`); `#__jed_queue_jobs` + `JobHandlerRegistry` run arbitrary job types. | VEL gets its own `plg_task_vel` (team ownership) but may enqueue `#__jed_queue_jobs` jobs. |
| Extension identity | `#__jed_extensions` stores `extension_types` (e.g. "component,plugin") and nothing else about identity. `#__jed_extensions_files.meta` is always `''`. `UpdateServerXmlParser` reads only `<version>` and `<downloadurl>`. `com_abandonware` reserved `identity_key` (P1-28) for the same gap. | The JED cannot today answer "which `element` is listing 1234?". §6.9.3 proposes the fix. |
| Manifest parsing | `Parser\File` reads a zip's manifest (name, version, author, types). | Extendable to return `element/type/folder/client`. |
| Legacy data | `plg_sampledata_jed_migrate` flattens RSForms 5/7/10/11/12 (VEL report/update forms, several generations) into `old_rsform*` staging tables — "nothing consumes these tables yet". RSTicketsPro department 5 (VEL, 218 tickets / 627 messages) is **excluded** from the JED ticket migration "as accepted loss". The published VEL entries themselves (the current `/vulnerable-extensions/` articles) are **not** referenced anywhere in the migration. | §6.10 covers this. |
| Audit pipeline | `Audit\AuditPipeline` runs phpstan + Claude over an uploaded package via Docker. | Optional future hook: run on a reported extension (§10). |

### 2.2 Gaps against the brief

| Brief requirement | Gap today |
|---|---|
| Ticket-based intake by e-mail **and** web form, for reporters/developers/users | No e-mail intake anywhere; ticket engine needs an account; VEL correspondence would be visible to all JED ticket staff. |
| Multi-product advisories, version ranges, 1-n CWE, references, credits | Legacy schema is flat, one product, one CWE string. |
| Auto-maintained `dateReserved/datePublished/dateUpdated` | Not modelled. |
| Joomla identity (`type/element/folder/client_id`) | Not modelled anywhere in the package. |
| Generate CVE JSON | Not present. |
| NVD and CNA feeds | Not present. |
| Filterable list with CVSS/severity filters | Legacy list filters on title only; no CVSS 3.1/4.0, no severity. |
| RSS and filterable JSON, check by extension identifiers | Not present (legacy site's JSON feed is a JED3 artefact not carried into this repo). |

---

## 3. Architecture decision: standalone `com_vel`

**Recommendation: a standalone component `com_vel`, installed by `pkg_jed` alongside `com_jed` and
`com_abandonware`.** [DECISION D-1]

Why standalone rather than embedded in `com_jed`:

1. **It is already the project's settled position.** P0-04 / decision 8.13 removed `com_vel` from
   the JED and stated the new VEL is a separate component owned by the VEL team. `com_abandonware`
   was built on exactly that basis and `com_jed` has been written to "stay out of the way" of
   `/vulnerable-extensions/`.
2. **Different team, different ACL, different release cadence.** Who may publish a security
   advisory is not who may approve a listing. A separate component gives the VEL team its own
   `access.xml`, its own options, its own menus and its own update path without touching JED
   permissions.
3. **Confidentiality.** Unpublished vulnerability details and proof-of-concept material must not sit
   in tables and views every JED moderator can open. Separate tables under separate ACL are the
   simplest way to guarantee that, and they keep privacy export/erasure (P1-18) a query against one
   component's tables.
4. **The JED is optional to a VEL entry.** Most affected products should resolve to a JED listing,
   but an advisory about an unlisted extension, a template, or a library has to be a full citizen.
   Modelling the VEL as a JED sub-feature makes the unlisted case the awkward one.
5. **The shared pieces are already designed to be shared.** `#__jed_extensions` (to link a product
   to a listing), `#__jed_tickets` (to hand the JED team an action), `#__jed_queue_jobs`,
   `JedAccessHelper`/`Privilege::REPORT` (abuse controls), the manifest `Parser`, and
   `com_abandonware`'s `CaseService::raiseUnlisted()` are all reachable from a sibling component —
   `com_abandonware` proves it.

What embedding would have bought — one install, one namespace — is already provided by `pkg_jed`.

**Consequence:** the legacy `Vel*` code and the four `#__jed_vel_*` tables are removed from
`com_jed` once `com_vel` is live and any data of value has been migrated (§6.10, task M-04).

---

## 4. Scope, users and roles

### 4.1 In scope

- Everything in `VEL2.md` §2–§6.
- A reporter-facing status/reply channel that does not require a JED account (D-3).
- A developer-facing "fix notice" form (the successor of the legacy developer-update form).
- Legacy-compatible JSON feed for the deprecation period.
- Migration of published legacy VEL entries and of the RSForms staging data where it has value.
- Removal of the legacy VEL code from `com_jed`.
- A reference consumer: a small `plg_system_velcheck` (or `plg_task`) for a Joomla site that calls
  the check API and shows affected extensions in the backend. Built last, as the acceptance test of
  the API contract, and shippable separately from `pkg_jed`.

### 4.2 Out of scope

- Joomla **core** vulnerabilities (the JSST Security Centre remains the source; the VEL links to it).
- Running a full CNA workflow (rejections, disputes, ADP containers). Reserve + publish via CVE
  Services is a Phase 3 stretch; the generator is the committed deliverable.
- A general-purpose helpdesk. Communication is scoped to VEL cases.
- Automated exploit verification or code auditing (may use the existing audit pipeline later, §10).
- Translations beyond `en-GB` (strings are translatable; no other language is delivered).

### 4.3 Users

| Actor | Needs |
|---|---|
| **Reporter** (security researcher, site owner, developer) | Submit a report with minimal friction, attach PoC, get a reference number, follow status, reply securely, be credited (or stay anonymous). |
| **Extension developer / vendor** | Be notified, see exactly what was reported (with reporter consent), respond, submit a fix notice, see the draft advisory before publication. |
| **VEL team member** | Triage queue, assign, communicate, edit advisory structured data, classify, score, publish, retire. |
| **VEL team lead** | Publish, reserve/submit CVEs, configure policy numbers, run/inspect feeds. |
| **JED team** | Know when a listed extension has an open advisory; act on the listing (block/unpublish). |
| **Site administrator (public)** | Browse/filter the list, read advisories, subscribe by RSS, check their own site automatically. |
| **Tool / integrator** | Stable JSON with version ranges and Joomla identities; ETags; no auth for public data. |

### 4.4 Roles and ACL (`administrator/components/com_vel/access.xml`)

| Action | Meaning | Default groups |
|---|---|---|
| `core.admin` | Component options | Super Users |
| `core.manage` | Open the backend; see advisories, reports, cases | VEL Team |
| `core.create` / `core.edit` / `core.delete` / `core.edit.state` | Ordinary editorial work (draft ↔ ready) | VEL Team |
| `vel.publish` | `ready → published`, `published → retired` | VEL Lead |
| `vel.cve` | Generate CVE JSON, reserve IDs, submit records | VEL Lead |
| `vel.feeds` | Run feed imports manually, accept/ignore imported candidates | VEL Team |
| `vel.communicate` | Read and write case messages; see reporter identity and attachments | VEL Team |
| `vel.jed` | Open a JED ticket / request a block on a listing | VEL Team |

The split mirrors `com_abandonware` (`abandonware.mark` separate from `core.edit`): putting a
statement about somebody's product in public is a distinct act from editing the draft.

---

## 5. Decisions required before build

| ID | Decision | Recommendation | Why it matters |
|---|---|---|---|
| **D-1** | Standalone `com_vel` vs embedded in `com_jed` | **Standalone** (§3) | Everything below assumes it. |
| **D-2** | Where the ticket/communication layer lives: (A) reuse `#__jed_tickets` fully, with a new `TicketType::Vulnerability` and a visibility filter added to `com_jed`; (B) `com_vel` owns a small case-messaging model (`#__vel_messages`, `#__vel_participants`) and uses a JED ticket only to hand the JED team a listing action; (C) external helpdesk, `com_vel` stores a ticket URL | **(B)**, with the schema keeping `external_ticket_url` so (C) can be used in the interim | (A) puts embargoed content in a shared queue and needs guest participants bolted onto an engine another team owns; (C) gives no structured link between a conversation and the advisory it produces. (B) is ~2 tables and one thread layout, and reuses the JED ticket engine for the one JED-facing thing. |
| **D-3** | Guest reporting: must a reporter have a JED account? | **No.** Web form accepts guests with e-mail verification (double opt-in link), and logged-in users without it; rate limits per IP hash and per e-mail; honeypot + captcha plugin. | Researchers will not register to report. `com_abandonware` requires login because its abuse surface is a competitor's reputation; a VEL report's abuse surface is smaller and its value higher. |
| **D-4** | Inbound e-mail: (i) IMAP polling by a scheduled task using a pure-PHP IMAP client, (ii) inbound-parse webhook from the mail provider, (iii) none — e-mails are handled in the mailbox and linked by hand | **(i)** for Phase 2, with (ii) as an alternative if infrastructure prefers it; Phase 1 ships with (iii) | `ext-imap` left PHP core in 8.4; a library such as `webklex/php-imap` (MIT, pure PHP) avoids it. Needs a mailbox (`vel@…`) and credentials from infrastructure. |
| **D-5** | Public identifier | `VEL-<id>` (zero-padded to 4, e.g. `VEL-0042`) in addition to the CVE ID; URL `/vulnerable-extensions/<id>-<alias>` | Not every entry has a CVE; links must never depend on one. |
| **D-6** | CVE `versions[]` style in the generator: structured (`version` + `lessThanOrEqual`) vs the Vulnogram-style single string `"1.0.0-15.0.0"` seen in the brief's example | **Structured**, option to emit the string style | Structured ranges are what consumers (NVD, OSV, GitHub) parse; the string form is lossy. |
| **D-7** | Disclosure policy numbers: vendor response window, default embargo, auto-publish on embargo expiry | Config options with defaults 14 days first response, 90 days to fix, no auto-publish | Policy, not code; same reasoning as `com_abandonware`'s `grace_days`. |
| **D-8** | Legacy JSON feed compatibility window | 12 months from go-live, with a `Deprecation`/`Sunset` header | Third-party checkers consume the current feed. |
| **D-9** | Whether the JED check API accepts a posted inventory at all, or only serves the full dataset for local matching | **Both**: full dataset is the primary, privacy-preserving path; the posted-inventory endpoint is a convenience with no storage | A site's inventory is a fingerprint; never persisted, never logged beyond a count. |
| **D-10** | Show a VEL badge on the JED listing page and block the listing automatically when a *live* (unpatched) advisory is published? | Badge: yes, automatically. Block: **never automatic** — `com_vel` opens a JED ticket requesting it; JED team acts with `jed.block` reason code `VL1` | Blocking is a JED decision with commercial effect (cf. 4.10). |

---

## 6. Functional requirements

### 6.1 Data model

All tables are prefixed `#__vel_`. Foreign keys are scoped to `com_vel`'s own tables; links into
`#__jed_extensions`, `#__jed_tickets`, `#__jed_abandonware_cases` and `#__users` are plain indexed
columns for the reasons `install.mysql.utf8.sql` gives (a constraint would block deleting a user or
a listing, or silently cascade). Every file is safe to run twice (`CREATE TABLE IF NOT EXISTS`,
`INSERT IGNORE`) and schema changes after go-live go through `sql/updates/mysql/<version>.sql`
(P1-30).

```
 #__vel_advisories 1 ──< #__vel_advisory_products 1 ──< #__vel_advisory_versions
        │ 1
        ├──< #__vel_advisory_cwes          >── #__vel_cwe (reference list)
        ├──< #__vel_advisory_references
        ├──< #__vel_advisory_credits
        ├──< #__vel_reports                 (intake; nullable advisory_id until accepted)
        ├──< #__vel_messages ──> #__vel_participants
        ├──< #__vel_attachments
        ├──< #__vel_advisory_history        (JSON snapshot per published revision)
        └──< #__vel_feed_items              (imported candidates; nullable advisory_id)
               └── #__vel_feed_runs
```

#### 6.1.1 `#__vel_advisories` — the advisory (one per vulnerability)

| Column | Type | Notes |
|---|---|---|
| `id` | int unsigned PK | Public id `VEL-%04d`. |
| `title` | varchar(255) | Short, human: "XSS in tag-provided inputs in various Regular Labs extensions". The CVE title prefix is generated (§6.5). |
| `alias` | varchar(255) | SEF slug, unique. |
| `state` | enum `draft, ready, published, retired` | Editorial workflow (brief §3.1.A). See §6.2. |
| `status` | enum `live, patched, abandoned` | Public classification (brief §4.1). Derived by default — `patched` when every product has a `fixed_version`; `abandoned` when linked to an abandonware case concluded `abandoned` or set by hand; else `live`. Editors may override; override is recorded. |
| `description` | mediumtext (HTML) | The single advisory description (brief §3.1.C). Used for `descriptions[0].value` (stripped to text) and `supportingMedia` (HTML). |
| `solution` | text | Optional "what to do" (update to X / uninstall). Exported to CVE `solutions[]`. |
| `workaround` | text | Optional. CVE `workarounds[]`. |
| `cvss_version` | enum `3.1, 4.0` NULL | |
| `cvss_vector` | varchar(128) | `CVSS:3.1/AV:N/...` — validated by regex; score derived server-side. |
| `cvss_score` | decimal(3,1) NULL | Derived from the vector; stored for filtering/sorting. |
| `severity` | enum `none, low, medium, high, critical` NULL | Derived from score (0 / 0.1–3.9 / 4.0–6.9 / 7.0–8.9 / 9.0–10.0). Stored for filtering. |
| `exploited` | enum `unknown, no, yes` | "Known to be exploited in the wild". |
| `discovery` | enum `UNKNOWN, INTERNAL, EXTERNAL, USER` | CVE `source.discovery`. |
| `cve_id` | varchar(20) NULL UNIQUE | `CVE-YYYY-NNNNN…`, validated. NULL until reserved/known. |
| `cve_state` | enum `none, reserved, published, rejected` | Mirror of `cveMetadata.state`. |
| `cve_assigner` | varchar(64) | `Joomla` when ours; otherwise the CNA shortName from the feed (e.g. `VulDB`). |
| `date_reserved` | datetime NULL | **Auto**: set when `cve_state` becomes `reserved` (or copied from the feed). |
| `date_published` | datetime NULL | **Auto**: first `ready → published`. Never edited by hand. |
| `date_updated` | datetime NULL | **Auto**: every save while `published`. |
| `date_public` | datetime NULL | Planned/actual disclosure date (embargo). Publishing before it asks for confirmation. |
| `date_reported` | datetime NULL | First report received. |
| `date_vendor_notified` | datetime NULL | Auto from the first outbound message to a vendor participant; editable. |
| `date_fixed` | datetime NULL | |
| `external_ticket_url` | varchar(255) | For D-2 option (C) or any external tracker. |
| `jed_ticket_id` | int unsigned NULL | The JED-team ticket opened via §6.9.2, at most one. |
| `abandonware_case_id` | int unsigned NULL | Link to `#__jed_abandonware_cases`. |
| `source` | enum `manual, report, cna, nvd, legacy` | What created the row. |
| `internal_notes` | mediumtext | Never public. |
| `published_json` | json NULL | Last CVE JSON generated (for diffing). |
| `created`, `created_by`, `modified`, `modified_by`, `checked_out`, `checked_out_time` | standard | |
| `hits` | int unsigned | Optional; reuse `com_jed` hit aggregation if wanted. |

Indexes: `(state, status, date_published)`, `(severity)`, `(cvss_score)`, `(cve_id)`, `(alias)`,
`(abandonware_case_id)`. Full-text index on `(title, description)` for the public search.

#### 6.1.2 `#__vel_advisory_products` — affected products (brief §3.1.E/F, §5.3)

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `advisory_id` | FK → advisories ON DELETE CASCADE | |
| `vendor` | varchar(255) | e.g. `regularlabs.com`. Pre-filled from the JED listing's `developer_url` host. |
| `product` | varchar(255) | e.g. `Modals`. The exported product name is generated: "`{product}` extension for Joomla" (§6.5). |
| `extension_id` | int unsigned NULL | `#__jed_extensions.id`. Snapshot semantics as `com_abandonware`: the free-text columns stay as entered. |
| `supplier_url` | varchar(255) | When no JED listing. |
| `ext_type` | enum `component, module, plugin, template, library, package, file, language` NULL | Joomla identity. |
| `element` | varchar(100) NULL | `com_foo`, `mod_foo`, `foo` (for plugins the element is the plugin name), `tpl` name for templates. Lower-cased, validated `^[a-z0-9_\-]+$`. |
| `folder` | varchar(50) NULL | Plugin group; NULL for other types. |
| `client_id` | tinyint NULL | 0 site, 1 administrator, NULL = either/unknown. |
| `fixed_version` | varchar(50) | First safe version; empty when none. |
| `patch_url` | varchar(255) | Download/patch URL from the developer. |
| `default_status` | enum `unaffected, affected, unknown` | CVE `defaultStatus`; default `unaffected`. |
| `ordering` | int | |

Unique key `(advisory_id, ext_type, element, folder, client_id)` where identity is not NULL.

#### 6.1.3 `#__vel_advisory_versions` — version ranges per product

| Column | Type | Notes |
|---|---|---|
| `id` | PK | |
| `product_id` | FK → products ON DELETE CASCADE | |
| `version_from` | varchar(50) | Inclusive. `0` or `*` allowed meaning "from the first version". |
| `version_to` | varchar(50) | Inclusive ("last affected"). `*` = unbounded (no fix / abandoned). |
| `version_type` | enum `custom, semver` | CVE `versionType`; default `custom` (Joomla versions are not strict semver). |
| `ordering` | int | |

Only affected ranges are stored (brief §3.1.E). Matching semantics in §8.6.

#### 6.1.4 `#__vel_advisory_cwes` and `#__vel_cwe`

`#__vel_advisory_cwes(advisory_id, cwe_id varchar(16), description varchar(255), ordering)` — the
1-n tuple list the brief asks for. `description` is copied from `#__vel_cwe.name` at selection time
but is editable (CVE records carry the text, and MITRE occasionally renames).

`#__vel_cwe(cwe_id PK, name, status enum(stable, draft, deprecated), view_ids varchar)` — seeded from
MITRE's CWE list (the "Software Development" view 699 plus the 4.x "Top 25"), refreshable by an
admin task that downloads `cwec_latest.xml.zip` (R-03). Used by an autocomplete field.

#### 6.1.5 `#__vel_advisory_references`

`(id, advisory_id, url varchar(1024), name varchar(255), tags json, ordering)`. Tag vocabulary is
the CVE 5.x reference-tag list (`vendor-advisory`, `third-party-advisory`, `patch`, `product`,
`exploit`, `issue-tracking`, `release-notes`, `technical-description`, `mitigation`, `related`,
`media-coverage`, `government-resource`, `mailing-list`, `permissions-required`, `signature`,
`customer-entitlement`, `broken-link`, `not-applicable`) plus `x_jed` for the JED listing link,
which the generator adds automatically when `extension_id` is set.

#### 6.1.6 `#__vel_advisory_credits` (brief §3.1.H)

`(id, advisory_id, name varchar(255), role enum(finder, reporter, analyst, coordinator,
remediation_developer, remediation_reviewer, remediation_verifier, tool, sponsor, other), user_id
NULL, report_id NULL, is_public tinyint, ordering)`. `is_public = 0` keeps the credit internal
(anonymous reporter); the generator only exports public credits. The role list is the CVE 5.x
`credits[].type` vocabulary.

#### 6.1.7 `#__vel_reports` — intake (one submission, one person, one moment)

Modelled on `#__jed_abandonware_reports`: all personal data about the reporter lives **here and in
`#__vel_participants` only**, so privacy export/erasure is a query against two tables.

| Column | Notes |
|---|---|
| `id` | Public reference `VELR-%05d` quoted in e-mails. |
| `kind` | enum `vulnerability, fix_notice`. One table, two forms (§6.4.2). |
| `advisory_id` | NULL until accepted/attached. |
| `state` | enum `new, verifying, triaged, accepted, duplicate, rejected, spam`. `verifying` = e-mail not yet confirmed (D-3). |
| `reporter_user_id`, `reporter_name`, `reporter_email`, `reporter_organisation`, `reporter_role` enum(`researcher, developer, site_owner, other`) | |
| `reporter_wants_credit` tinyint, `reporter_credit_name` | |
| `share_with_developer` tinyint | The legacy `pass_details_ok`. |
| `extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`, `developer_email` | What the reporter said. |
| `vulnerability_class` | CWE id from a short pick-list (the legacy "exploit type" list mapped to CWE: SQLi → CWE-89, XSS → CWE-79, path traversal → CWE-22, information disclosure → CWE-200, plus RCE → CWE-94, CSRF → CWE-352, auth bypass → CWE-287, file upload → CWE-434, other). |
| `description`, `steps_to_reproduce`, `impact` | text |
| `actively_exploited` enum(`unknown,no,yes`), `publicly_disclosed` tinyint, `disclosure_url` | |
| `developer_contact_status` | The legacy five options, as enum `not_contacted, contacted_no_reply, contacted_replied, i_am_developer, fixed_publicly`. |
| `patch_url`, `fixed_version` | Used by `fix_notice` and by `fixed_publicly`. |
| `tracking_reference` | e.g. an existing CVE or advisory id. |
| `additional_info` | text |
| `consent_to_process`, `consent_time` | Evidence with a date, as in `com_abandonware`. |
| `reporter_ip_hash` varbinary(32) | Hash, never the address (P1-12 reasoning). |
| `verify_token` varchar(64), `verified_time` | D-3 double opt-in. |
| `legacy_form_id`, `legacy_submission_id` | Idempotency key for the RSForms import; UNIQUE together. |
| `created`, `created_by`, `modified`, `modified_by` | |

#### 6.1.8 Case communication — `#__vel_participants`, `#__vel_messages`, `#__vel_attachments` (D-2 option B)

- `#__vel_participants(id, advisory_id NULL, report_id NULL, role enum(reporter, vendor, team,
  other), name, email, user_id NULL, access_token varchar(64) UNIQUE, token_expires, notify
  tinyint, created)` — a person in a case. A reporter is created from the report; a vendor from the
  JED listing's owner/maintainers (`#__jed_extensions.owner`, `#__jed_extensions_maintainers` with
  `state = 1`, never `created_by`, as `CaseService::mailOwner()` reasons) or from
  `developer_email`. The token is what lets a guest read and reply at
  `/vulnerable-extensions/case/<token>` without an account.
- `#__vel_messages(id, advisory_id NULL, report_id NULL, participant_id NULL (NULL = team),
  direction enum(in, out, internal), channel enum(web, email, system), subject, body mediumtext,
  message_id varchar(255) (RFC 5322 id for threading), in_reply_to, visibility enum(team,
  reporter, vendor, all), created, created_by)`.
- `#__vel_attachments(id, message_id NULL, report_id NULL, filename, stored_name, mime, size,
  sha256, created)` stored **outside the web root** (`<JPATH_ROOT>/../vel_private/` or a configured
  path), served only through a controller that checks `vel.communicate` or a matching participant
  token. PoC files are never public.

Every e-mail the system sends carries `[VEL-0042]` / `[VELR-00123]` in the subject and a
`Message-ID` recorded in `#__vel_messages`, so inbound replies thread by `In-Reply-To` first and by
subject token second.

#### 6.1.9 `#__vel_feed_runs` and `#__vel_feed_items` (§6.6)

- `#__vel_feed_runs(id, source enum(cna, nvd), started, finished, window_from, window_to,
  items_seen, items_new, items_updated, status enum(ok, partial, failed), message)`.
- `#__vel_feed_items(id, source, external_id varchar(32) (the CVE id), assigner varchar(64),
  fetched, external_updated, content_hash char(64), payload json, classification enum(joomla_cna,
  joomla_extension_other_cna, joomla_core, unrelated, unknown), decision enum(pending, created,
  merged, ignored), advisory_id NULL, decided_by, decided_time)`. UNIQUE `(source, external_id)`.

#### 6.1.10 `#__vel_advisory_history`

`(id, advisory_id, revision int, snapshot json, state, created, created_by)` — written on every
publish and on every save of a published advisory. Gives "what did we say on date X", a diff view,
and the `dateUpdated` audit trail the CVE Program expects.

### 6.2 Advisory lifecycle

```
            ┌────────┐  ready   ┌────────┐  publish  ┌───────────┐  retire  ┌─────────┐
 report ──▶ │ draft  │ ───────▶ │ ready  │ ────────▶ │ published │ ───────▶ │ retired │
 feed   ──▶ │        │ ◀─────── │        │           │           │          │         │
 manual ──▶ └────────┘  reopen  └────────┘           └───────────┘ ◀──────  └─────────┘
                                                            (republish, vel.publish)
```

| Transition | Permission | Side effects |
|---|---|---|
| `draft → ready` | `core.edit.state` | Validation: title, description, ≥1 product with ≥1 version range, ≥1 CWE, status set. CVSS is required unless `severity` is set by hand with a reason (feeds sometimes arrive without metrics). |
| `ready → published` | `vel.publish` | Sets `date_published` (first time) and `date_updated`; writes a history revision; notifies participants who opted in; if a product has `extension_id`, raises the JED badge (§6.9.1) and, for `status = live`, offers to open the JED ticket (§6.9.2); warns if `date_public` is in the future. |
| `published` save | `core.edit` | Sets `date_updated`; new history revision. |
| `published → retired` | `vel.publish` | Stays reachable at its URL with a "withdrawn" notice and a reason (`duplicate`, `not_a_vulnerability`, `cve_rejected`, `other`); excluded from lists and RSS; JSON v2 includes it with `"withdrawn": "<date>"` for one year so consumers drop it. |
| `ready → draft`, `retired → published` | `core.edit.state` / `vel.publish` | |

`status` (`live/patched/abandoned`) changes independently of `state`; a published advisory moving
from `live` to `patched` is an update, not a republish, and is the single most common edit.

The JED-facing rule: an advisory is public **only** in `published`. Nothing in `draft`/`ready` is
readable without `core.manage`, including by URL, by JSON and by the check API.

### 6.3 Backend administration

Everything the brief asks for in §3, plus the intake and feed views. Layouts follow
`com_abandonware/tmpl/case/edit.php` (Joomla tabs, `joomla.edit.global` sidebar) so the two
components feel like one product.

#### 6.3.1 Views

| View | Purpose |
|---|---|
| **Dashboard** (`view=dashboard`, default) | Counters: new reports, unverified reports, drafts, ready for publication, embargoes due in 7 days, vendor-response overdue, feed candidates pending, last feed run status. Each is a link to the filtered list. |
| **Advisories** (`view=advisories`) | Columns: id (`VEL-0042`), title, products (comma list, vendor once), CVE, status badge, state, severity/CVSS, `date_published`, `date_updated`. Filters: state, status, severity, vendor, has-CVE, source, assigned. Search across title/description/vendor/product/CVE. Ordering default `date_updated DESC`. Toolbar: New, Publish/Unpublish (state), Retire, Generate CVE JSON (single), Batch, Trash. |
| **Advisory** (`view=advisory&layout=edit`) | Tabs: **Advisory** (title, status, description, solution, workaround, dates read-only, exploited, discovery) · **Affected products** (repeatable subform: vendor, product, JED listing (`Listingref`-style resolver + "copy from listing" button), supplier URL, identity fields, fixed version, patch URL, nested repeatable version ranges) · **Classification** (CWE autocomplete repeatable; CVSS vector with embedded calculator, derived score/severity read-only) · **References** (repeatable: url, name, tags multiselect) · **Credits** (repeatable: name, role, public) · **Reports & messages** (linked reports, the message thread, reply box with recipient visibility, attachments) · **CVE** (cve_id, cve_state, assigner, dates; buttons *Generate JSON*, *Download*, *Copy*, *Validate*, Phase 3: *Reserve ID*, *Submit/Update record*) · **Notes** (internal) · **History** (revisions, diff). Right sidebar: state, created/modified, assigned_to, JED ticket link, abandonware case link. |
| **Reports** (`view=reports`) | The intake queue. Columns: ref, kind, state, extension, reporter (masked unless `vel.communicate`), received, verified, advisory. Actions: *Create advisory from report* (pre-fills products/identity/CWE/credit), *Attach to advisory…*, *Mark duplicate*, *Reject*, *Spam* (also bans via `jed.user.ban` if an account). |
| **Report** (`view=report`) | Read-only report with the thread and attachments; the same actions. |
| **Feed items** (`view=feeditems`) | Imported candidates with classification and a side-by-side of the external record vs. the matched advisory (if any). Actions: *Create advisory*, *Merge into…* (field-by-field accept), *Ignore*. Filters: source, classification, decision. |
| **Feed runs** (`view=feedruns`) | Log with *Run now* buttons per source (`vel.feeds`). |
| **CWE list** (`view=cwes`) | Reference table with *Refresh from MITRE*. |
| **Options** | §6.3.3. |

#### 6.3.2 Form behaviour

- Repeatable structures use Joomla `subform` fields (`multiple="true"`,
  `layout="joomla.form.field.subform.repeatable-table"`), with the version-range subform nested
  inside the product subform. The model normalises them into the child tables on save and
  re-hydrates them on load; nothing structured is stored as a JSON blob on the advisory row, because
  the check API and the public filters query by product, element and version.
- "Copy from JED listing": given a listing id/alias/URL (the `ListingrefField` resolver — a select
  over ~14,900 listings is not workable), fills vendor (host of `developer_url`), product (name),
  supplier URL, and — when available from §6.9.3 — identity. A later enhancement adds an AJAX
  autocomplete against `#__jed_extensions.name`.
- CVSS: a client-side calculator for 3.1 and 4.0 (FIRST's reference calculators are BSD-licensed
  and can be bundled under `media/com_vel/js/`); the server recomputes the score from the vector on
  save and refuses a vector/score mismatch.
- Dates `date_reserved/published/updated` render read-only. A Super User may correct
  `date_reserved`/`date_published` for migrated rows through a separate "Correct dates" task with a
  required reason written to `internal_notes`.
- All text input is filtered as `com_abandonware` does (`filter="string"`, lengths capped);
  `description` uses the editor with `JComponentHelper::filterText`.

#### 6.3.3 Component options (`config.xml`)

| Fieldset | Option | Default |
|---|---|---|
| Policy | `vendor_first_response_days` | 14 |
| | `disclosure_days` (default embargo length from vendor notification) | 90 |
| | `auto_publish_on_embargo` | No |
| | `require_cvss_to_publish` | Yes |
| Intake | `allow_guest_reports` | Yes (D-3) |
| | `guest_verification_required` | Yes |
| | `report_rate_limit` / `report_rate_window_hours` | 5 / 24 |
| | `captcha` | Site default |
| | `attachment_max_mb`, `attachment_types` | 10, `zip,txt,md,log,png,jpg,pdf,asc` |
| | `attachment_path` | `<configured private path>` |
| | `pgp_public_key` (textarea) | shown on the report page |
| E-mail | `from_address`, `from_name`, `reply_to` | |
| | `inbound_mode` (`off, imap, webhook`), IMAP host/port/user/password/folder, `webhook_secret` | `off` |
| CVE | `cna_org_id` | `6ff30186-7fb7-4ad9-be33-533e7b05e586` |
| | `cna_short_name` | `Joomla` |
| | `cve_services_env` (`test, prod`), `cve_api_org`, `cve_api_user`, `cve_api_key` | test |
| | `cve_data_version` | `5.1` or `5.2` (D-6 / R-02) |
| | `title_prefix` | `Joomla Extension - {vendor} - ` |
| | `product_suffix` | ` extension for Joomla` |
| Feeds | `nvd_api_key`, `nvd_enabled`, `nvd_lookback_days` | —, Yes, 7 |
| | `cna_enabled`, `cna_source` (`cvelist_github, cve_services`), `cna_lookback_days` | Yes, `cvelist_github`, 7 |
| | `feed_auto_create` (create `draft` automatically for `joomla_cna` items) | Yes |
| Public | `list_limit`, `show_cvss_in_list`, `legacy_feed_enabled`, `legacy_feed_sunset_date` | 25, Yes, Yes, go-live + 12 months |
| | `api_cache_seconds` | 300 |
| JED | `jed_badge_enabled`, `jed_open_ticket_on_publish` (`never, ask, always`) | Yes, ask |

### 6.4 Intake and communication (brief §2.1, §6 steps 1–2)

#### 6.4.1 Vulnerability report form — `/vulnerable-extensions/report`

Successor of `velreportform.xml`, cut down to what a reporter knows. Sections: *About you* (name,
e-mail, organisation, role, credit preference) · *The extension* (JED listing lookup with "not in
the JED" fallback → name, version, URL, developer name/e-mail) · *The vulnerability* (class
pick-list, description, steps to reproduce, impact, actively exploited?, publicly disclosed? + URL,
tracking reference) · *Developer contact* (the five options; patch URL / fixed version when
applicable) · *Attachments* (PoC) · *Consent* (processing; sharing details with the developer).
Guests: honeypot, captcha, e-mail verification link (report stays `verifying`, invisible in the
queue, purged after 7 days unverified). Logged-in users: `JedAccessHelper::assertMay($userId,
Privilege::REPORT)` as `com_abandonware` does, so an existing JED ban applies. Rate limit per
`reporter_ip_hash` and per e-mail. On success: reference `VELR-00123`, a confirmation mail with the
participant token link, and a `#__vel_messages` system entry.

#### 6.4.2 Fix notice form — `/vulnerable-extensions/fix-notice`

Successor of `veldeveloperupdateform.xml`. Fields: advisory/CVE/VEL reference (optional; lookup),
extension, fixed version, update notice URL, changelog URL, download URL, message, contact details,
consent. Creates a `#__vel_reports` row with `kind = fix_notice` attached to the advisory when the
reference resolves, and a message in the thread. Editors confirm and set `fixed_version` on the
product, which flips `status` to `patched`.

#### 6.4.3 E-mail intake (D-4)

- Phase 1: the public pages show `vel@…` and the PGP key; mail is handled in the mailbox; editors
  paste what matters into the thread as `channel = email`. `external_ticket_url` is available.
- Phase 2: `plg_task_vel` routine `vel.mailfetch` polls IMAP every N minutes (or a webhook
  controller receives parsed mail). For each message: thread by `In-Reply-To`/`References` → by
  `[VEL-…]`/`[VELR-…]` token → else create a `#__vel_reports` row of `kind = vulnerability`, state
  `new`, `channel = email`, with the sender as participant. Attachments are stored per §6.1.8 with
  type/size limits. Auto-reply with the reference (once per thread). Loops are prevented by
  `Auto-Submitted`/`Precedence: bulk` checks and a per-sender daily cap.

#### 6.4.4 Conversation model

- Team replies from the advisory/report thread; choose visibility (`reporter`, `vendor`, `all`,
  `internal`). Outbound mail uses `MailTemplate` ids `com_vel.message`, `com_vel.report_received`,
  `com_vel.report_verify`, `com_vel.vendor_notification`, `com_vel.advisory_published`,
  `com_vel.fix_received` — seeded by `script.php` exactly as `com_jed` seeds its templates.
- Vendor notification: one click creates vendor participants from the JED listing owner and
  maintainers (and/or `developer_email`), sends `com_vel.vendor_notification` containing the
  report details the reporter consented to share, the disclosure date, and a token link to the
  private case page where the vendor can reply and preview the draft advisory. Sets
  `date_vendor_notified` and `date_public = now + disclosure_days` if unset.
- Guest case page `/vulnerable-extensions/case/<token>`: shows the messages visible to that
  participant, allows a reply and attachment, never shows internal notes, the other party's
  e-mail address, or the advisory before it is published (vendors see the draft; reporters see
  the draft only if the team ticks "share draft with reporter").
- Overdue indicators on the dashboard: vendor not responded within
  `vendor_first_response_days`; embargo within 7 days; embargo passed and still `ready`.

### 6.5 Generate CVE JSON (brief §4.3)

A `CveRecordBuilder` service turns one advisory into a CVE Record Format 5.x document (full
mapping in Appendix C). Principles:

- **Derive, don't store.** Title = `title_prefix` rendered + advisory title. Description value =
  title + " - " + text of `description`; `supportingMedia` carries the HTML. Product name =
  `product` + `product_suffix`. Affected `versions[]` from the stored ranges (D-6). `references`
  get `x_jed` + the listing URL added when `extension_id` is set. `credits` from public credits.
  `problemTypes` from CWEs. `metrics` from CVSS when present. `source.discovery` from `discovery`.
  `providerMetadata`/`cveMetadata` from options and the advisory's dates. `x_generator.engine` =
  `Joomla VEL <version>`.
- **Validate before you hand it over.** The CVE 5.x JSON schema (MIT-licensed, from
  `CVEProject/cve-schema`) is bundled under `administrator/components/com_vel/schema/` and the
  output is validated with `justinrainbow/json-schema` or `opis/json-schema` (added to
  `composer.json`). Validation errors are shown inline; a record that does not validate can still be
  downloaded but is labelled.
- **Two shapes.** *CNA container only* (what CVE Services `POST /cve/{id}/cna` expects) and *full
  record* (what the brief's example shows and what Vulnogram opens). Both downloadable; both
  available at `GET /vulnerable-extensions/api/v2/cve/CVE-YYYY-NNNNN.json` for published advisories.
- **Phase 3, behind `vel.cve`:** *Reserve CVE ID* (`POST /cve-id`) writes `cve_id`, `cve_state =
  reserved`, `date_reserved`; *Submit record* (`POST`/`PUT /cve/{id}/cna`) on publish, with the
  response stored in `published_json`. Test environment first (`cve_services_env`).

### 6.6 Inbound feeds (brief §3.2)

Both feeds run as `plg_task_vel` routines, write to `#__vel_feed_items`, and never publish anything
by themselves. An item becomes an advisory only through the *Feed items* view, except that
`feed_auto_create = Yes` creates a `draft` (not `ready`) for Joomla-CNA items to save the re-keying.
Idempotent by `(source, external_id)` + `content_hash`: an unchanged record is skipped; a changed
one becomes `pending` again with a diff against the linked advisory.

#### 6.6.1 CNA feed — records assigned by the Joomla CNA (and other CNAs' Joomla-extension records)

- **Source (default):** the `CVEProject/cvelistV5` GitHub repository. Poll `cves/deltaLog.json`
  (hourly deltas, ~30 days retained) [VERIFY R-02], collect `new`/`updated` CVE ids since the last
  run's `window_to`, fetch each record's JSON from the raw GitHub URL. No credentials required;
  a GitHub token is optional for rate limit headroom.
- **Alternative:** CVE Services `GET /api/cve?assigner_short_name=Joomla&time_modified.gt=…`
  (requires the CNA credentials) [VERIFY R-02].
- **Classification:** `joomla_cna` when `cveMetadata.assignerShortName == "Joomla"` (or
  `assignerOrgId` == our org id) and no affected product is Joomla core; `joomla_core` when
  affected vendor/product is `Joomla`/`Joomla! CMS` (ignored — JSST's domain); `joomla_extension_
  other_cna` when another CNA's record mentions Joomla in `affected[].platforms`,
  `affected[].collectionURL`, product name, or description; otherwise `unrelated` (not stored).
- **Mapping:** Appendix C in reverse. ADP containers (`containers.adp[]`, e.g. CISA Vulnrichment)
  are read for CVSS/CWE when the CNA container has none, with the provenance noted.
- **Matching to existing advisories:** by `cve_id`; else by (vendor host + product name +
  overlapping version range) fuzzy match, shown as a suggestion, never auto-merged.

#### 6.6.2 NVD feed — enrichment and discovery

- **Source:** NVD API 2.0 `https://services.nvd.nist.gov/rest/json/cves/2.0` with
  `lastModStartDate`/`lastModEndDate` windows (≤120 days), `resultsPerPage` up to 2000, `apiKey`
  header; rate limits 5 req/30 s without key, 50 req/30 s with key — the routine sleeps ≥6 s
  between calls regardless [VERIFY R-01].
- **Discovery query:** `keywordSearch=Joomla` over the window; post-filter: drop records whose
  CPE vendor is `joomla` and product `joomla\!` (core); keep records with any
  `configurations[].nodes[].cpeMatch[].criteria` whose `target_sw` component is `joomla`, or whose
  description matches `/joomla/i` and `/(extension|component|module|plugin|template)/i`
  [VERIFY R-01: whether `virtualMatchString` can express "target_sw = joomla" and replace the
  keyword query].
- **Enrichment of our own records:** for every published advisory with a `cve_id`, fetch
  `?cveId=` and copy, when we have none, `cvssMetricV31`/`cvssMetricV40` (Primary first, then
  Secondary), `weaknesses` (CWE ids), CPE-derived version bounds (`versionStartIncluding`,
  `versionEndIncluding`, `versionEndExcluding` → our inclusive `version_to` by decrementing only
  when the editor confirms), and references with tags. Shown as a pending merge.
- **Caveat to document:** since early 2024 the NVD has a large enrichment backlog; many recent
  CVEs carry no NVD CPE or CVSS for weeks or months. The CNA feed is therefore the primary source
  of truth; NVD is secondary.

#### 6.6.3 Common

- Every run is logged in `#__vel_feed_runs`; failures raise a Joomla `Log` warning under category
  `com_vel` and show on the dashboard.
- A *Run now* button (`vel.feeds`) triggers a single run synchronously with a 60-second budget.
- Research tasks R-01/R-02 deliver the exact endpoints, parameters, sample payloads and fixtures
  checked into `tests/fixtures/` before the parser tasks start.

### 6.7 Public frontend (brief §4.1–4.2)

Routes (via `components/com_vel/src/Service/Router.php`, a `RouterView` like `com_abandonware`'s;
`/vulnerable-extensions` is a menu item created by the sample data plugin):

| URL | View |
|---|---|
| `/vulnerable-extensions/` | `advisories` list; default filter `status = live` with tabs **Live · Patched · Abandoned · All** |
| `/vulnerable-extensions/<id>-<alias>` | `advisory` detail |
| `/vulnerable-extensions/report` | report form |
| `/vulnerable-extensions/fix-notice` | fix-notice form |
| `/vulnerable-extensions/case/<token>` | participant case page (§6.4.4) |
| `/vulnerable-extensions/?format=feed&type=rss` (and `atom`) | RSS/Atom |
| `/vulnerable-extensions/json-feed/` | legacy-compatible JSON (D-8) |
| `/vulnerable-extensions/api/v2/…` | JSON v2 (§6.8) |

#### 6.7.1 List view

- Filters (form above the table, GET parameters so URLs are shareable): full-text **search** over
  title, description, vendor, product (one query using the full-text index plus `LIKE` on the
  product table); **severity** (multi); **CVSS min / max** (0.0–10.0, step 0.1); **status** tab;
  optional **vendor** and **extension type**; **published after/before**.
- Default sort `date_published DESC`; sortable by CVSS, title, vendor, updated.
- Columns: title (link), product name(s) (all, vendor shown once), vendor, CVSS score, severity
  badge (colour + text, not colour alone), `date_published`, status badge, CVE id when set.
- Pagination from component option; RSS and JSON links in the page head (`<link rel="alternate">`)
  and the footer.
- Empty-state text per tab. No JavaScript required for filtering (progressive enhancement only).

#### 6.7.2 Detail view

Everything stored for the record (brief §4.2): title; `VEL-0042` and CVE id (linked to cve.org
and nvd.nist.gov); status and severity; CVSS vector with a score bar and a link to the FIRST
calculator pre-filled; **Affected** table (product, vendor, Joomla identity, affected ranges,
fixed version, JED listing link, patch URL); **What to do** (solution/workaround, or the generic
"update to ≥ fixed version / uninstall until fixed" text); full advisory text; **Classification**
(CWE ids linked to MITRE); **References** with tags; **Credits** (public ones); **Timeline**
(reported, vendor notified, fixed, reserved, published, updated); **Downloads** (JSON v2, CVE JSON);
"Report a fix for this advisory" and "Report a different vulnerability" buttons. Retired advisories
show the withdrawal notice above a greyed record.

Schema.org markup (`TechArticle` with `datePublished`/`dateModified`) and Open Graph tags.
Canonical URL is the `<id>-<alias>` form.

### 6.8 Machine-readable outputs (brief §4.4–4.5)

#### 6.8.1 RSS / Atom

Joomla `FeedView` for the `advisories` view. Items: title (`[Live] Modals — XSS …`), link,
description (status, vendor, products, affected ranges, fixed version, severity/CVSS, CVE), `pubDate
= date_published`, `guid = canonical URL`, `category` = status and severity. Respects the same
filters as the list (`?status=live`, `?severity=critical`), so subscribers can subscribe to "critical
and live" only. 50 items, `date_published DESC`; a second feed `?sort=updated` for "recently
updated".

#### 6.8.2 JSON v2 — `/vulnerable-extensions/api/v2/`

Contract in Appendix B. Endpoints:

| Endpoint | Purpose |
|---|---|
| `GET advisories.json` | Paged list; same filters as the HTML list plus `element[]`, `type`, `folder`, `client_id`, `vendor`, `cve`, `updated_since`, `include_withdrawn`. |
| `GET advisories/<id>.json` / `advisories/CVE-….json` | One advisory, full structure. |
| `GET cve/CVE-….json` | The CVE 5.x record (§6.5). |
| `GET dataset.json` | **The complete published dataset** in one file (gzip-friendly, ETag), regenerated on publish and cached — the primary path for site-side checkers (D-9). |
| `POST check` | Body: `{ "extensions": [ { "type", "element", "folder", "client_id", "version" } ] }` → matching advisories per entry. Stateless; inventory never stored; only a request counter is logged. Max 500 entries per call. |
| `GET index.json` | Discovery document: endpoints, dataset ETag/last-modified, dataset size, sunset of the legacy feed. |

All responses: `Content-Type: application/json; charset=utf-8`, `ETag`, `Last-Modified`,
`Cache-Control: public, max-age=<api_cache_seconds>`, CORS `Access-Control-Allow-Origin: *` for
GET. Served by `JsonView` classes on the site side (no API-application token friction, stays under
the `/vulnerable-extensions/` path, CDN-cacheable). The Joomla Web Services API can wrap the same
models later if wanted.

#### 6.8.3 Legacy JSON feed — `/vulnerable-extensions/json-feed/`

Reproduces the field names of the current feed (to be captured verbatim in R-04 from the live
site; believed to be a flat array of article-like objects with title, alias, category (Live /
Resolved / Unresolved), publish date, intro text and the legacy "Risk level / Vulnerable versions /
Patched version / Exploit type / Recommendation / Update notice / CVE / Discovered by" fields).
Adds `Deprecation` and `Sunset` headers and a `_deprecated` property pointing at v2. Removed after
`legacy_feed_sunset_date` (D-8).

### 6.9 Integrations with `com_jed` and `com_abandonware`

#### 6.9.1 JED listing badge

`com_jed`'s extension detail view calls a small `Jed\Component\Vel\Site\Helper\VelBadge::for(int
$extensionId)` (guarded by `ComponentHelper::isEnabled('com_vel')`, the way `com_abandonware`
handlers tolerate absence) and shows a warning panel "This extension has a known vulnerability —
see VEL-0042" for `published` + `live` advisories, an info panel for `patched` ("affected versions
≤ X; update to Y"). Also shown on the developer dashboard listing row. One indexed query; cached
per request. This is a small change in `com_jed`, listed under the JED coordination tasks.

#### 6.9.2 JED ticket (TicketType 11)

When an advisory is published with `status = live` and a product has `extension_id`, `com_vel`
(per `jed_open_ticket_on_publish`) inserts a `#__jed_tickets` row with `linked_item_type = 11`
(`TicketType::Vulnerability`, new; 4–6 stay retired), `linked_item_id = advisory id`, subject
"Published advisory VEL-0042 affects listing <name>", body containing **only public information**
(the public URL, affected versions, fixed version) and the requested action (block with reason
`VL1`, or watch). A `VulnerabilityTicketHandler` in `com_jed/src/Ticket/` renders the master data
(public fields) and offers *Block listing* (`jed.block`, reason `VL1 — Listed on the VEL`, new
block reason + mail template) and *Open in VEL*. At most one ticket per advisory (`jed_ticket_id`).
Closing the advisory (`patched`/`retired`) appends a note to the ticket and sets it `Resolved`, as
`CaseService::closeTicket()` does. Nothing confidential ever enters `#__jed_tickets`.

#### 6.9.3 Extension identity (shared)

The JED has no `element` for its listings, and both VEL (`§5.3`) and abandonware (`identity_key`)
need one. Proposed shared enhancement in `com_jed` (can ship independently of VEL 2):

- New table `#__jed_extension_identities(extension_id, ext_type, element, folder, client_id,
  source enum(manifest, updateserver, manual), confirmed tinyint, created, updated)` — a listing may
  have several (a package).
- `UpdateServerXmlParser` additionally captures `<element>`, `<type>`, `<folder>`, `<client>` from
  each `<update>`; `UpdateCheckService` upserts identities with `source = updateserver`.
- `Parser\File` exposes `getIdentities()` read from the manifest (package `<files>`, plugin
  `group`, `client` attribute), used when a package is uploaded.
- `com_vel`'s "copy from JED listing" reads this table; the check API can then also match on a
  JED listing id.

Until that ships, VEL editors enter identity by hand (the update-server XML of the extension is the
quickest place to read it from; the form's help text says so).

#### 6.9.4 Abandonware

- *Advisory → case:* a `live` advisory whose vendor has not responded within
  `vendor_first_response_days` offers "Raise abandonware signal": calls
  `CaseService::raise($extensionId, CaseSource::MANUAL, …)` or `raiseUnlisted()` and stores
  `abandonware_case_id`. A new `CaseSource::VEL` value is a one-line addition in `com_abandonware`.
- *Case → advisory:* when a case linked to an advisory reaches `abandoned`, the advisory's `status`
  becomes `abandoned` (hook in `CaseService::markAbandoned()` guarded by `isEnabled('com_vel')`, or
  a nightly reconciliation in `plg_task_vel` — the latter avoids a code change in
  `com_abandonware` and is the default).

#### 6.9.5 Other package plumbing

- `plg_task_vel` routines: `vel.feed_cna`, `vel.feed_nvd`, `vel.mailfetch`, `vel.housekeeping`
  (purge unverified reports > 7 days, expire tokens, regenerate `dataset.json`, reconcile
  abandonware status, send overdue digests).
- `plg_privacy_vel`: export/remove for `#__vel_reports`, `#__vel_participants`, `#__vel_messages`,
  `#__vel_attachments` by user id **and** by e-mail (guests), following `plg_privacy_jed`.
  Removal anonymises the reporter (name → "Reporter #id", e-mail → NULL, attachments deleted,
  credits with `is_public = 0` deleted) and keeps the advisory.
- `plg_actionlog_vel`: publish, retire, CVE reserve/submit, feed accept/ignore, vendor notified,
  block requested.
- `plg_finder_vel` (optional): index published advisories for site search.
- `mod_vel_latest` (site, optional): latest N published advisories for the JED home page.
- `plg_sampledata_jed2` gains the `/vulnerable-extensions` menu items (list, report, fix-notice)
  — the path it already promises to leave free.
- `pkg_jed.xml` gains `com_vel`, `plg_task_vel`, `plg_privacy_vel`, `plg_actionlog_vel`.

### 6.10 Migration and removal of the legacy VEL code

#### 6.10.1 Data sources

| Source | Where | Action |
|---|---|---|
| Published legacy VEL entries (the current `/vulnerable-extensions/` content, believed to be `com_content` articles in Live/Resolved/Unresolved categories with structured intro text) | Live JED3 database — **not in this repo** | R-04 inventories the exact source and fields; M-01 writes `plg_sampledata_jed_migrate` steps that parse them into advisories (`source = legacy`, `state = published`, dates preserved via the "Correct dates" path), one product each, version ranges from "Vulnerable versions"/"Patched version", CVE id when present. Parsing quality is reported per row; unparsable rows are imported as `draft` with the raw text in `internal_notes`. |
| RSForms submissions (forms 5, 10, 11 reports; 7, 12 developer updates) | Already staged as `old_rsform*` by `rsforms.sql` | M-02 imports into `#__vel_reports` (`kind` by form, `legacy_form_id/legacy_submission_id` as the idempotency key), state `triaged`, consent from `consentuse`, IP hashed, reporter e-mail kept only where consent was given (P1-18 reasoning). Linked to migrated advisories by `vulExtnName` + date proximity where unambiguous. |
| RSTicketsPro department 5 (218 tickets, 627 messages) | JED3 database; excluded by `tickets.sql` as "accepted loss" | **[DECISION]** import into `#__vel_messages`/`#__vel_participants` (recommended — it is VEL correspondence and now has a home) or confirm the loss. |
| `#__jed_vel_*` tables in this repo | Empty in production (never fed) | Dropped in M-04 after a row-count check. |

#### 6.10.2 URL continuity

Legacy advisory URLs (`/vulnerable-extensions/live-vel/<id>-<alias>` etc. — exact patterns from
R-04) are mapped to the new canonical URLs with 301s. The migration step generates core
`com_redirect` rows (`#__redirect_links`); if the package's own URL-mapping work (referenced in the
code as P1-25, not present in this repository yet) lands with a different mechanism, the VEL
mapping joins it rather than keeping a second one. The feed URL is preserved (§6.8.3).

#### 6.10.3 Removal from `com_jed` (M-04, JED-team coordination)

Delete: `src/{Controller,Model,Table,View}/Vel*`, `forms/vel*.xml`, `forms/filter_vel*.xml`,
`tmpl/vel*`, `layouts/vulnerableitem/*`, `layouts/ticket/*vel*`, site `src/*/Vel*`, `tmpl/vel*`,
`forms/vel*.xml`, `media/com_jed/css/jedvel.css`, the `vulnerable` toolbar button in
`JedHelper::addConfigToolbar()`, language strings `COM_JED_VEL_*`, seed rows 4–6 in
`#__jed_ticket_linked_item_types`, the four `CREATE TABLE #__jed_vel_*` blocks (with a
`sql/updates/mysql/<version>.sql` that drops them) and the uninstall entries. Update the
`TicketType` comments to point at 11.

---

## 7. Non-functional requirements

| Area | Requirement |
|---|---|
| **Confidentiality** | Unpublished advisories, reports, messages, attachments and reporter identities are readable only with `core.manage`/`vel.communicate` or a valid participant token. Attachments are outside the web root. No confidential field is ever copied into `#__jed_tickets`, the action log, the finder index, or any feed. Tokens are 32 random bytes, hashed at rest (`sha256`), expire (default 180 days, renewable by a new mail), and are revoked on privacy removal. |
| **Integrity** | `VelService` (the analogue of `CaseService`) is the only writer of `state`, `status`, the auto dates and the CVE fields; controllers, feeds and tasks call it. Invariants enforced there and tested: `date_published` is set exactly once; `ready` requires the validation set in §6.2; `published` output always validates against the CVE schema or is flagged; `cve_id` unique. |
| **Privacy (GDPR)** | Lawful basis = consent with timestamp (reports) and legitimate interest (vendor contact). IP stored only as a hash. Guest e-mails purged with unverified reports after 7 days. Export/erasure via `plg_privacy_vel` by user id and by e-mail. Retention of closed reports configurable (default: keep; messages are the disclosure record). |
| **Abuse resistance** | Rate limits (per IP hash, per e-mail, per account), honeypot, captcha plugin, file-type allow-list, antivirus hook point (optional ClamAV via the audit Docker runner), `Privilege::REPORT` and bans honoured for accounts, no user-controlled HTML rendered unescaped anywhere public. |
| **Availability / performance** | Public list < 200 ms at 10k advisories (indexes in §6.1); `dataset.json` and the feeds cached and ETag'd; `POST check` ≤ 500 entries and one query per distinct `(type, element, folder)`; feeds run in background tasks with time budgets and resume markers; a feed failure never affects the public site. |
| **Accessibility** | WCAG 2.1 AA like the rest of the JED: severity conveyed by text and colour; forms with labels/descriptions; keyboard-operable subforms (Joomla core). |
| **Observability** | `Log` category `com_vel`; feed runs table; dashboard counters; action log entries for every public-facing change. |
| **Compatibility** | Joomla 6.0+, PHP 8.3+, no `Factory::getDbo()`, container-resolved DB as the rest of the package; MySQL 8 / MariaDB 10.6 (JSON columns, generated columns not required). |
| **Code standards** | PSR-12 + Joomla codestyle as the package; namespaces `Jed\Component\Vel\{Administrator,Site}`; docblocks with `@since 4.0.0` (package version) — or `1.0.0` if `com_vel` is versioned separately [DECISION]; every policy number in `config.xml`, not in a constant. |
| **Testing** | PHPUnit for `CveRecordBuilder` (schema-validated against fixtures including the brief's example), `VersionRange::matches()`, severity derivation, CVSS 3.1/4.0 scoring, NVD and cvelistV5 parsers (fixtures), `VelService` transitions, identity normalisation, legacy feed shape. System tests (Cypress, as Joomla core) for the report form, publish flow, list filters, JSON endpoints. |
| **Documentation** | Admin guide (workflow, feeds, CVE), reporter page ("How to report"), developer page ("You have been contacted by the VEL"), API page (v2 contract + check example + deprecation of the legacy feed). |

---

## 8. Technical design

### 8.1 Package layout

```
administrator/components/com_vel/
  vel.xml  access.xml  config.xml  script.php
  services/provider.php
  sql/install.mysql.utf8.sql  sql/uninstall.mysql.utf8.sql  sql/reset.mysql.utf8.sql  sql/updates/mysql/
  schema/cve-record-5.x.json                 (bundled CVE schema)
  forms/advisory.xml  forms/filter_advisories.xml  forms/report.xml  forms/filter_reports.xml
        forms/feeditem.xml  forms/filter_feeditems.xml  forms/message.xml
  language/en-GB/com_vel.ini  com_vel.sys.ini
  src/Enum/{AdvisoryState,AdvisoryStatus,Severity,CveState,Discovery,CreditRole,ReferenceTag,
            ExtensionType,ReportKind,ReportState,FeedSource,FeedClassification,FeedDecision}.php
  src/Table/{Advisory,AdvisoryProduct,AdvisoryVersion,AdvisoryCwe,AdvisoryReference,AdvisoryCredit,
             Report,Participant,Message,Attachment,FeedItem,FeedRun,Cwe}Table.php
  src/Model/{Dashboard,Advisories,Advisory,Reports,Report,Feeditems,Feeditem,Feedruns,Cwes}Model.php
  src/Controller/{Display,Advisories,Advisory,Reports,Report,Feeditems,Feedruns,Cve,Message,Attachment}Controller.php
  src/View/<view>/HtmlView.php
  src/Service/VelService.php                 (lifecycle + invariants; only writer of state)
  src/Service/ReportService.php              (intake rules: consent, rate limit, verification)
  src/Service/MessageService.php             (threads, participants, tokens, outbound mail)
  src/Service/VendorResolver.php             (owner/maintainers/developer_email → participants)
  src/Service/JedBridge.php                  (ticket 11, badge data, listing lookup, identities)
  src/Service/AbandonwareBridge.php
  src/Cve/{CveRecordBuilder,CveRecordValidator,CveServicesClient,TitleConventions}.php
  src/Cvss/{Cvss31,Cvss40,Severity}.php
  src/Version/{VersionNormaliser,VersionRange,Matcher}.php
  src/Feed/{FeedRunner,FeedSourceInterface,CnaFeedSource,NvdFeedSource,CvelistClient,NvdClient,
            RecordClassifier,AdvisoryMerger}.php
  src/Mail/{Inbound,ImapFetcher,WebhookReceiver,ThreadResolver,MimeParser}.php
  src/Field/{Cwe,Cvss,ExtensionType,Listingref(reuse/extend from com_jed),ReferenceTags}Field.php
  src/Helper/VelHelper.php
  tmpl/<view>/…   layouts/…
components/com_vel/
  src/Service/Router.php
  src/Controller/{Display,Report,Fixnotice,Case,Api}Controller.php
  src/Model/{Advisories,Advisory,Report,Fixnotice,Case,Dataset,Check,Legacyfeed}Model.php
  src/View/Advisories/{HtmlView,FeedView,JsonView}.php  View/Advisory/{HtmlView,JsonView}.php
  src/View/{Report,Fixnotice,Case}/HtmlView.php  View/{Dataset,Check,Legacyfeed,Index}/JsonView.php
  src/Helper/VelBadge.php
  forms/report.xml  forms/fixnotice.xml  forms/casereply.xml  forms/filter_advisories.xml
  tmpl/advisories/{default.php,default.xml,default_filter.php}  tmpl/advisory/default.php  …
  language/en-GB/com_vel.ini
media/com_vel/{css,js}/   (severity badges, CVSS calculators, subform helpers)
plugins/task/vel/   plugins/privacy/vel/   plugins/actionlog/vel/   (plugins/finder/vel optional)
modules/mod_vel_latest/ (optional)
```

### 8.2 Service layer

`VelService` follows `CaseService` line for line in spirit: constructor-injected
`DatabaseInterface`, `option()` for policy numbers, `loadLanguage()` for task context, public
methods `transition()`, `publish()`, `retire()`, `setStatus()`, `attachReport()`,
`setCve()`/`reserveCve()`, `raiseJedTicket()`, `closeJedTicket()`, `linkAbandonware()`, with a
private `writeState()` that nothing else calls. Everything that moves an advisory — the backend
toolbar, the feed merger, the fix-notice form, the housekeeping task — calls it.

### 8.3 Forms and persistence

The `advisory.xml` form declares `products` as a `subform` (multiple) whose own XML declares
`versions` as a nested `subform`. `AdvisoryModel::save()` validates the nested arrays, writes the
advisory row, then replaces child rows inside a transaction (delete-and-insert keyed by advisory id
is acceptable at this scale and simpler than diffing; ids of children are not referenced
elsewhere except `credits.report_id`, which is re-linked by the `report_id` carried in the
subform). `getItem()` re-hydrates. `Table` classes exist for each child for `check()`/`store()`
reuse and for the history snapshot.

### 8.4 Routing

`RouterView` with views `advisories` (list, menu item), `advisory` (key `id`, parent
`advisories`), `report`, `fixnotice`, `case` (key `token`), `api` (segments `v2/<resource>`),
`legacyfeed`. `getAdvisorySegment()` returns `<id>-<alias>`; `getAdvisoryId()` accepts a bare id, an
`<id>-<alias>`, a `VEL-0042` or a `CVE-…` (resolved by lookup) so that all four forms of link work.

### 8.5 JSON views

`JsonView` subclasses set the document type, call the model, and `echo json_encode(...)` with
`JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`; headers via `$app->setHeader()`; `If-None-Match`
handled to return 304. Errors are JSON (`{"error": {"code", "message"}}`) with the right status.

### 8.6 Version matching (`Version\Matcher`)

1. Normalise both sides: trim; lower-case; strip a leading `v`; replace `_`/spaces with `.`; map
   `alpha/beta/rc/dev/pl` words to PHP `version_compare` tokens; drop a trailing free-text suffix
   after the first whitespace ("1.0.0 Free" → "1.0.0"), recording that it happened.
2. `*`/empty `version_from` = -∞; `*`/empty `version_to` = +∞.
3. `affected = compare(v, from) >= 0 && compare(v, to) <= 0` using `version_compare`.
4. A version that cannot be normalised returns `unknown`, which the check API reports as
   `"match": "unknown"` rather than silently "no".
5. Identity match before version match: `(type, element)` must equal, `folder` must equal for
   plugins, `client_id` equal unless the advisory's is NULL. Element comparison is case-insensitive.

### 8.7 Inbound mail (Phase 2)

`ImapFetcher` (on `webklex/php-imap`, pure PHP, MIT — added to `composer.json`) or
`WebhookReceiver` (HMAC-verified POST) produce a normalised `InboundMessage` (from, subject,
text/html body, `Message-ID`, `In-Reply-To`, `References`, attachments). `ThreadResolver` maps it to
a message row; `MimeParser` is only needed for the webhook path if the provider posts raw MIME.
Processed UIDs/ids are recorded so a re-run never duplicates.

### 8.8 Dependencies to add (`administrator/components/com_vel/composer.json`, vendored like `com_jed`)

`opis/json-schema` (or `justinrainbow/json-schema`) for CVE validation; `webklex/php-imap`
(Phase 2 only). Both permissively licensed. HTTP through Joomla's `Joomla\Http\HttpFactory`.

---

## 9. Work breakdown

IDs: **R** research, **S** schema/foundation, **A** admin, **I** intake/communication, **C** CVE,
**F** feeds, **P** public, **J** JED/abandonware integration, **M** migration/removal, **Q**
quality/docs. "Depends" lists hard prerequisites. Sizes are T-shirt (S ≤ 1 day, M 2–3 days, L ≤ 1
week, XL > 1 week) for one engineer familiar with the package.

### Phase 0 — Decisions and research (parallel, ~1 week)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| R-00 | Reviewer signs off D-1…D-10 and the open questions in §10 | — | — | This document updated to "APPROVED" with decisions recorded. |
| R-01 | **NVD API research**: confirm endpoint, parameters (`keywordSearch`, `virtualMatchString` semantics, date windows), rate limits, response shape for CVSS 3.1/4.0, weaknesses, CPE version bounds, reference tags; obtain an API key; capture 5 fixtures (a Joomla-extension CVE with CPE, one without, a core CVE, an unrelated "Joomla" keyword hit, a rejected CVE). Write `docs/feeds/nvd.md`. | M | — | Document + fixtures committed; §6.6.2 [VERIFY] items resolved. |
| R-02 | **CVE Program research**: confirm `cvelistV5` delta mechanism and retention, raw URL pattern, record schema version in use (5.1.x vs 5.2), CVE Services endpoints for reserve/publish/update and list-by-assigner, test-environment credentials from the Joomla CNA; download the JSON schema; capture fixtures (the brief's example, a record with ADP metrics, a record from another CNA about a Joomla extension). Write `docs/feeds/cve.md` and `docs/cve-conventions.md` (title/product/description conventions, reference tags). | M | — | Document + fixtures + schema committed; D-6 confirmed. |
| R-03 | **CWE source**: pick the MITRE download (`cwec_latest.xml.zip`) and the subset to seed; write the seed SQL and the refresh parser spec. | S | — | `sql/seed_cwe.sql` with ≥ 400 entries. |
| R-04 | **Legacy inventory**: on the live JED3 database identify where published VEL entries live, their fields and URL patterns, the exact shape of `/vulnerable-extensions/json-feed/`, and known consumers of it; count RSTicketsPro department-5 rows. Write `docs/migration/vel-legacy.md` with the field mapping (Appendix A completed). | M | — | Document with samples; D-8 window confirmed. |
| R-05 | Infrastructure: mailbox for `vel@…`, IMAP or webhook availability, private attachment path, outbound mail domain/DKIM. | S | — | Values for `config.xml` known. |

### Phase 1 — Foundation and editorial core (≈ 3–4 weeks)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| S-01 | Component skeleton: manifests, `services/provider.php`, `VelComponent`, `access.xml`, `config.xml`, language files, admin menu, `pkg_jed.xml` entry, build script inclusion. | M | R-00 | Installs/uninstalls cleanly; backend opens to an empty dashboard. |
| S-02 | Schema: `install.mysql.utf8.sql`, `uninstall`, `reset`, `updates/mysql/` seed; all §6.1 tables; CWE seed (R-03); mail-template seeds in `script.php`. | M | S-01, R-03 | Fresh install twice without error; `SchemaUpdatePathTest`-style test passes. |
| S-03 | Enums, Tables, `VelService` with transitions/invariants and unit tests. | L | S-02 | Illegal transitions throw; dates auto-set exactly as §6.2. |
| S-04 | `Cvss\Cvss31`, `Cvss\Cvss40`, `Severity`, `Version\*` with unit tests (fixtures from FIRST examples; messy Joomla version strings). | M | S-01 | 100 % of fixture vectors score correctly; matcher tests pass. |
| A-01 | Advisories list view + filters + toolbar + batch. | M | S-03 | Reviewer can find any advisory by CVE/vendor/product/severity. |
| A-02 | Advisory edit form: all tabs except Reports/CVE; nested subforms; save/load normalisation; CWE autocomplete field; reference tag field; CVSS field with calculator; "copy from JED listing". | XL | S-03, S-04 | The brief's Regular Labs example can be entered in full and round-trips. |
| A-03 | Publish/retire flows with validation messages, history snapshots, history tab with diff. | M | A-02 | `ready` blocked until complete; publish sets dates once. |
| A-04 | Dashboard view with counters and links. | S | A-01 | |
| A-05 | `plg_actionlog_vel`. | S | A-03 | Publish/retire/CVE events logged. |
| P-01 | Site router, menu items in sample data, list view with tabs/filters/sort/pagination, severity badges CSS. | L | A-03 | §6.7.1 acceptance: every filter works without JS; URLs shareable. |
| P-02 | Detail view incl. retired notice, schema.org, downloads. | M | P-01 | §6.7.2 complete. |
| P-03 | RSS/Atom `FeedView` honouring filters. | S | P-01 | Validates in the W3C feed validator. |
| P-04 | JSON v2: `advisories.json`, `advisories/<id>.json`, `dataset.json`, `index.json`; ETag/304; caching; CORS. | M | P-01 | Appendix B contract met; contract test passes. |
| P-05 | `POST check` endpoint + matcher; request-count-only logging; limits. | M | P-04, S-04 | Posting the fixture inventory returns the expected matches incl. `unknown`. |
| Q-01 | CI: phpcs, phpstan (level as package), PHPUnit for S-03/S-04/P-04/P-05. | S | S-03 | Green pipeline. |

**Milestone 1:** editors can create and publish multi-product advisories; the public list, detail,
RSS, JSON v2 and check API work. (Legacy feed and migration follow in Phase 4 so that Milestone 1
can be demonstrated on sample data.)

### Phase 2 — Intake and communication (≈ 3 weeks)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| I-01 | `ReportService`: consent, rate limits, `Privilege::REPORT`, ban check, guest verification tokens, IP hashing, purge of unverified. Unit tests. | M | S-03 | Rules from §6.4.1 enforced with readable refusals. |
| I-02 | Report form (site) with JED lookup/fallback, attachments (private storage + download controller), honeypot, captcha, confirmation mail with reference. | L | I-01 | A guest can file, verify, and receive `VELR-…`. |
| I-03 | Fix-notice form (site) resolving `VEL-`/`CVE-` references; creates `fix_notice` report + message. | M | I-01 | |
| I-04 | Reports queue + report view (admin); *Create advisory from report* (pre-fill), *Attach*, *Duplicate*, *Reject*, *Spam* (+ ban for accounts). | L | A-02, I-01 | Every report reaches a terminal state through the UI. |
| I-05 | `MessageService`, participants, tokens, thread layout in advisory/report views, reply with visibility, outbound `MailTemplate`s with `[VEL-…]` subject tokens and `Message-ID` recording. | L | I-04 | Two-way web correspondence with reporter and vendor. |
| I-06 | Vendor notification: `VendorResolver` (owner + accepted maintainers + `developer_email`), `com_vel.vendor_notification`, `date_vendor_notified`, embargo default. | M | I-05 | One click notifies and starts the clock. |
| I-07 | Participant case page `/case/<token>`: read thread, reply, attach, preview draft (vendor). | M | I-05 | Guest reply lands in the thread; no leakage of internal data (test). |
| I-08 | Dashboard overdue indicators; `vel.housekeeping` task (purges, token expiry, digests). | S | I-06 | |
| I-09 | `plg_privacy_vel` export/remove by user id and e-mail. | M | I-05 | Export contains every personal field; removal anonymises and deletes attachments. |
| I-10 | Inbound e-mail (D-4): `ImapFetcher` or `WebhookReceiver`, `ThreadResolver`, auto-reply, loop protection, `vel.mailfetch` routine; fixtures for threading by `In-Reply-To`, by subject token, and new-thread creation. | L | I-05, R-05 | A mail to `vel@…` becomes a report; a reply to a thread lands in it. |

**Milestone 2:** the brief's workflow steps 1–4 run end to end inside the component.

### Phase 3 — CVE (≈ 2 weeks)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| C-01 | `CveRecordBuilder` (CNA container + full record), `TitleConventions`, bundled schema + `CveRecordValidator`; unit tests against R-02 fixtures incl. the brief's example (byte-for-byte on the fields we control). | L | A-02, R-02 | Generated record validates; diff against the example is only dates/ids/generator. |
| C-02 | CVE tab UI: generate, view, copy, download (both shapes), validate, store `published_json`; public `api/v2/cve/CVE-….json`. | M | C-01 | |
| C-03 | `CveServicesClient`: reserve id, get record, post/put CNA container, against the **test** environment; toolbar buttons behind `vel.cve`; state/dates updated via `VelService`. | L | C-02 | Reserve + publish round-trip on the test instance. |
| C-04 | Documentation `docs/cve-workflow.md` for the VEL team (conventions, Vulnogram hand-off, when to use the API). | S | C-02 | |

### Phase 4 — Feeds, migration, legacy (≈ 3 weeks)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| F-01 | `FeedRunner`, `#__vel_feed_runs/items`, `RecordClassifier`, Feed items and Feed runs views, *Run now*. | M | S-03 | A fixture run produces classified pending items. |
| F-02 | `CnaFeedSource` + `CvelistClient` (deltaLog polling, per-record fetch, hashing, resume markers); `AdvisoryMerger` (create draft / merge with field-level accept); `vel.feed_cna` routine; auto-create for `joomla_cna`. | L | F-01, C-01, R-02 | Joomla CNA records appear as drafts within one run; re-runs are no-ops. |
| F-03 | `NvdFeedSource` + `NvdClient` (windows, paging, rate limiting, key), discovery query + post-filter, enrichment of our CVE ids; `vel.feed_nvd` routine. | L | F-01, R-01 | NVD CVSS/CWE/CPE bounds offered as merges; backlog caveat surfaced in UI. |
| F-04 | Feed documentation `docs/feeds/*.md` finalised with operational runbook (keys, schedules, failure handling). | S | F-02, F-03 | |
| M-01 | Legacy advisory migration step in `plg_sampledata_jed_migrate` (from R-04), with a per-row parse report and `com_redirect` rows for old URLs. | L | R-04, A-02 | ≥ 95 % of legacy entries import as `published` with ranges; the rest as `draft` with raw text. |
| M-02 | RSForms staging → `#__vel_reports` (forms 5/10/11 → vulnerability, 7/12 → fix_notice), idempotent. | M | I-01, R-04 | Counts match staging; re-run does not duplicate. |
| M-03 | Optional: RSTicketsPro department 5 → messages/participants. | M | I-05, R-04, decision | |
| P-06 | Legacy JSON feed at `/vulnerable-extensions/json-feed/` with `Deprecation`/`Sunset`; contract test against the captured sample. | M | R-04, P-04 | Existing consumers parse it unchanged. |
| M-04 | Remove legacy VEL code and tables from `com_jed` (§6.10.3) via a `com_jed` schema update; update `TicketType` comments; coordinate with JED team. | M | M-01, M-02 deployed | `grep -ri "vel" com_jed` finds only the bridge code. |

**Milestone 3:** go-live candidate — migrated data, feeds running, legacy feed served.

### Phase 5 — JED and abandonware integration, consumer (≈ 2 weeks)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| J-01 | `TicketType::Vulnerability = 11`, `VulnerabilityTicketHandler` in `com_jed`, block reason `VL1` + mail template, `JedBridge::raiseTicket/closeTicket`, `jed_open_ticket_on_publish` behaviours. | M | A-03 | Publishing a live advisory for a listed extension produces one JED ticket with public data only. |
| J-02 | `VelBadge` helper + panel in `com_jed` extension detail and developer dashboard. | S | P-02 | Badge shows for live/patched published advisories; nothing for drafts. |
| J-03 | Shared extension identities in `com_jed` (§6.9.3): table, `UpdateServerXmlParser` + `Parser\File` extensions, upsert in `UpdateCheckService`, "copy from listing" uses it. | L | S-01 | Identities populate from update servers on the next `jed.updatecheck`. |
| J-04 | Abandonware bridges: `CaseSource::VEL`, raise signal from advisory, nightly status reconciliation. | S | A-03 | |
| J-05 | Reference consumer `plg_system_velcheck` (separate extension): fetches `dataset.json` (or posts inventory when configured), caches, shows a backend message listing affected installed extensions with links. | M | P-04, P-05 | Installed on a test site with a deliberately "affected" extension, the notice appears. |
| J-06 | `mod_vel_latest` (optional) and `plg_finder_vel` (optional). | S | P-02 | |

### Phase 6 — Hardening and documentation (≈ 1 week)

| ID | Task | Size | Depends | Done when |
|---|---|---|---|---|
| Q-02 | Security review: token handling, attachment serving, visibility rules on case page, JSON endpoints with draft ids, SQL binding, XSS in advisory HTML, CSRF on all POSTs, rate limits. | M | all | Findings fixed; checklist in `docs/security-review.md`. |
| Q-03 | Performance pass with 10k synthetic advisories (sample data plugin `plg_sampledata_vel`). | S | P-01, P-04 | Targets in §7 met. |
| Q-04 | Accessibility pass on list, detail, forms, case page. | S | P-02, I-07 | No AA blockers. |
| Q-05 | Cypress system tests for the five key journeys (report → advisory → publish → JSON; fix notice → patched; feed item → draft; retire; check API). | M | all | Green in CI. |
| Q-06 | End-user docs: "How to report", "For developers", "API and feeds", admin guide. | M | all | Pages linked from the public views. |

---

## 10. Open questions

1. **Account or not for reporters (D-3)** — and whether a verified guest should be offered account
   creation afterwards to keep following their report.
2. **Where does the VEL team work day-to-day?** If they will keep an external mailbox/helpdesk
   regardless, Phase 2's inbound mail (I-10) may be deferred in favour of `external_ticket_url`.
3. **CVE data version** to emit by default (5.1.x vs 5.2) and whether CVE Services currently
   accepts 5.2 for the Joomla CNA (R-02).
4. **Vulnogram**: is the team's current tool; should the generator optimise for "open in
   Vulnogram" (full record) or for direct API submission (CNA container)? Both are planned; which
   is the toolbar default?
5. **Anonymity policy**: default credit = named unless the reporter opts out, or anonymous unless
   they opt in?
6. **Publishing advisories with no fix and an unresponsive vendor**: after `disclosure_days`, is
   publication automatic (config `auto_publish_on_embargo`) or always a human decision? The
   recommendation is human, with a loud dashboard item.
7. **Joomla core CVEs**: ignored entirely, or listed read-only with a link to the Security Centre
   so the list is "one place"? The brief's principle of centralisation argues for a read-only
   mirror; the JSST may prefer otherwise.
8. **Other ecosystems' CNAs** (VulDB, Patchstack, INCIBE, …) publish Joomla-extension CVEs. Are
   those imported as advisories (`cve_assigner` ≠ Joomla) or only linked as references? The design
   allows both; recommended: import, clearly marked "CVE assigned by X".
9. **Should the audit pipeline** (`Audit\AuditPipeline`) be offered as a one-click "run a code
   audit on the reported version" from a report? Cheap to wire, but the Claude/Docker cost and
   the legal position on downloading a reported third-party package need a policy.
10. **Versioning of `com_vel`**: package version (`4.0.0`) or its own (`1.0.0`)? Affects `@since`
    tags and `sql/updates` names.
11. **RSTicketsPro department 5** import (M-03): do it, or confirm "accepted loss".
12. **Retention** of closed reports/messages: indefinite (disclosure record) or time-boxed?

---

## Appendix A — Legacy → new field mapping

| Legacy (`#__jed_vel_vulnerable_item` / RSForms) | New | Notes |
|---|---|---|
| `vulnerable_item_name`, `vulnerable_item_version` | `products.product`, `versions.version_to` (if "vulnerable version" means last affected) | R-04 confirms semantics. |
| `title` | `advisories.title` | Strip legacy "[Live]"-style prefixes. |
| `internal_description` | `internal_notes` | |
| `public_description` | `description` | |
| `status` 0 Reported / 1 Live / 2 Patched / 3 Abandoned | `state = draft` (0) or `published`; `status = live/patched/abandoned` | |
| `report_id` | `reports.advisory_id` (reverse) | |
| `jed` (URL) | `products.extension_id` (resolve alias), `references` tag `x_jed` | |
| `risk_level` small/medium/high/veryhigh | `severity` low/medium/high/critical (when no CVSS) with `internal_notes` "severity set from legacy risk level" | |
| `start_version`, `vulnerable_version`, `patch_version` | `versions.version_from`, `versions.version_to`, `products.fixed_version` | |
| `recommendation` | `solution` | |
| `update_notice` | `references` tag `vendor-advisory`/`release-notes` | |
| `exploit_type` 1 SQLi / 2 info disclosure / 3 XSS / 4 traversal / 5 abandoned / 9 other | `cwes` CWE-89 / CWE-200 / CWE-79 / CWE-22 / (status abandoned) / none | |
| `exploit_other_description` | appended to `internal_notes` | |
| `xml_manifest`, `manifest_location`, `install_data` | `products.ext_type/element/folder/client_id` when parsable; else dropped | |
| `discovered_by` + `discoverer_public` | `credits` role `finder`, `is_public` | |
| `fixed_by` | `credits` role `remediation_developer` | |
| `coordinated_by` | `credits` role `coordinator` | |
| `jira` | `external_ticket_url` | |
| `cve_id`, `cwe_id` | `cve_id`, `cwes` | |
| `cvssthirty_base` / `_score` | `cvss_version = 3.1` if the vector says so, else `3.0` is recorded in notes and the score kept; `cvss_vector`, `cvss_score` | Temporal/environmental vectors go to `internal_notes`. |
| `alias` | `alias` | |
| `created/modified` | `date_published`/`date_updated` via "Correct dates" | |
| RSForms `passtodev` | `share_with_developer` | |
| RSForms `vulnType` 3rd-party extension / template / core | template → `ext_type = template`; core → report `rejected` with note | |
| RSForms `devradioselect` | `developer_contact_status` | |
| RSForms `trackdb`/`trackDbID` | `tracking_reference` | |
| RSForms `consentuse` | `consent_to_process` + `consent_time = DateSubmitted` | |
| RSForms `UserIP` | `reporter_ip_hash` | Hashed, never stored raw. |

## Appendix B — Public JSON v2 and check-API contract

### B.1 Advisory object

```json
{
  "id": 42,
  "vel_id": "VEL-0042",
  "cve_id": "CVE-2026-64795",
  "cve_assigner": "Joomla",
  "url": "https://extensions.joomla.org/vulnerable-extensions/42-xss-regular-labs",
  "title": "XSS vectors in tag-provided inputs in various Regular Labs extensions",
  "status": "patched",
  "severity": "medium",
  "cvss": { "version": "3.1", "vector": "CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N", "score": 5.4 },
  "exploited": "unknown",
  "description_html": "<p>…</p>",
  "description_text": "…",
  "solution": "Update to the versions listed.",
  "cwe": [ { "id": "CWE-79", "name": "Improper Neutralization of Input During Web Page Generation ('Cross-site Scripting')" } ],
  "affected": [
    {
      "vendor": "regularlabs.com",
      "product": "Modals",
      "jed_extension_id": 1234,
      "jed_url": "https://extensions.joomla.org/extension/modals/",
      "supplier_url": "https://regularlabs.com/",
      "identity": { "type": "plugin", "element": "modals", "folder": "system", "client_id": 0 },
      "affected_versions": [ { "from": "1.0.0", "to": "15.0.0" } ],
      "fixed_version": "15.0.1",
      "patch_url": "https://regularlabs.com/modals/download"
    }
  ],
  "references": [ { "url": "https://regularlabs.com/", "tags": ["product"] } ],
  "credits": [ { "name": "Jane Doe", "role": "finder" } ],
  "dates": {
    "reported": "2026-07-18T00:00:00Z",
    "vendor_notified": "2026-07-19T09:12:00Z",
    "reserved": "2026-07-20T18:16:31Z",
    "published": "2026-07-22T20:41:19Z",
    "updated": "2026-07-27T13:29:26Z",
    "fixed": "2026-07-21T00:00:00Z"
  },
  "withdrawn": null,
  "links": { "cve_json": ".../api/v2/cve/CVE-2026-64795.json", "html": "…" }
}
```

### B.2 List

`GET /vulnerable-extensions/api/v2/advisories.json?status=live&severity=high,critical&cvss_min=7&
element[]=com_foo&type=component&updated_since=2026-01-01T00:00:00Z&page=1&limit=50`

```json
{ "meta": { "total": 3, "page": 1, "limit": 50, "generated": "…", "dataset_etag": "…" },
  "data": [ { …advisory… } ] }
```

### B.3 Dataset

`GET /vulnerable-extensions/api/v2/dataset.json` → `{ "meta": { "generated", "count", "schema": "2.0" },
"data": [ …all published advisories… ] }` — also available as `dataset.json.gz` if the web server
does not compress.

### B.4 Check

```
POST /vulnerable-extensions/api/v2/check
Content-Type: application/json

{ "client": "joomla/6.0.1 plg_system_velcheck/1.0",
  "extensions": [
    { "type": "plugin", "element": "modals", "folder": "system", "client_id": 0, "version": "14.2.0" },
    { "type": "component", "element": "com_foo", "client_id": 1, "version": "2.0.0 Free" } ] }
```

```json
{ "meta": { "checked": 2, "dataset_etag": "…" },
  "results": [
    { "input": { "type": "plugin", "element": "modals", "folder": "system", "client_id": 0, "version": "14.2.0" },
      "match": "affected", "advisories": [ { "vel_id": "VEL-0042", "cve_id": "…", "status": "patched",
        "severity": "medium", "fixed_version": "15.0.1", "url": "…" } ] },
    { "input": { … }, "match": "unknown", "reason": "version_not_parsable", "advisories": [ … ] } ]
}
```

`match` ∈ `affected | not_affected | unknown`. For `unknown`, advisories for the identity are still
listed so the site can warn conservatively.

## Appendix C — Internal model → CVE Record 5.x mapping

| CVE path | From |
|---|---|
| `dataType` | `"CVE_RECORD"` |
| `dataVersion` | option `cve_data_version` |
| `cveMetadata.cveId` | `cve_id` |
| `cveMetadata.assignerOrgId` / `assignerShortName` | options `cna_org_id` / `cna_short_name` (or `cve_assigner` when foreign, in which case the record is not ours and the builder refuses with a message) |
| `cveMetadata.state` | `cve_state` upper-cased (`RESERVED`/`PUBLISHED`) |
| `cveMetadata.dateReserved` / `datePublished` / `dateUpdated` | `date_reserved` / `date_published` / `date_updated` (UTC, ms precision) |
| `containers.cna.providerMetadata` | `{ orgId, shortName, dateUpdated }` |
| `containers.cna.title` | `title_prefix` (rendered with first product's vendor) + `title` |
| `containers.cna.descriptions[0]` | `{ lang: "en", value: <title> + " - " + strip_tags(description), supportingMedia: [{ type: "text/html", base64: false, value: description_html }] }` |
| `containers.cna.affected[]` | one per product: `{ vendor, product: product + product_suffix, defaultStatus, versions: [ { version: from, lessThanOrEqual: to, status: "affected", versionType } … ], collectionURL: "https://extensions.joomla.org", packageName: element (when set), platforms: ["Joomla"] }` — D-6 decides the `versions` shape |
| `containers.cna.problemTypes[0].descriptions[]` | one per CWE: `{ lang: "en", type: "CWE", cweId, description }` |
| `containers.cna.metrics[]` | when `cvss_vector`: `{ format: "CVSS", scenarios: [{ lang: "en", value: "GENERAL" }], cvssV3_1 | cvssV4_0: { version, vectorString, baseScore, baseSeverity } }` |
| `containers.cna.references[]` | stored references + `{ url: JED listing, tags: ["product", "x_jed"] }` per linked listing + `{ url: VEL canonical URL, tags: ["third-party-advisory"] }` |
| `containers.cna.credits[]` | public credits `{ lang: "en", value: name, type: role }` (role `remediation_developer` → `"remediation developer"` etc.) |
| `containers.cna.source` | `{ discovery }` |
| `containers.cna.solutions[]` / `workarounds[]` | `solution` / `workaround` when set |
| `containers.cna.timeline[]` | reported, vendor notified, fixed, published (when set) |
| `containers.cna.datePublic` | `date_public` when set |
| `containers.cna.x_generator` | `{ engine: "Joomla VEL <version>" }` |

## Appendix D — External API research notes (to be confirmed in R-01/R-02)

**NVD API 2.0** — base `https://services.nvd.nist.gov/rest/json/cves/2.0`; parameters used:
`cveId`, `keywordSearch`, `keywordExactMatch`, `virtualMatchString`, `lastModStartDate`,
`lastModEndDate` (ISO-8601 with offset; window ≤ 120 days), `pubStartDate`/`pubEndDate`,
`resultsPerPage` (≤ 2000), `startIndex`, `noRejected`; header `apiKey`. Rate limits: 5 requests per
rolling 30 s without key, 50 with; HTTP 403 when exceeded. Response: `vulnerabilities[].cve` with
`id`, `sourceIdentifier`, `published`, `lastModified`, `vulnStatus`, `descriptions[]`,
`metrics.cvssMetricV31[] / cvssMetricV40[]` (`type: Primary|Secondary`, `cvssData.vectorString`,
`baseScore`, `baseSeverity`), `weaknesses[].description[].value` (CWE ids), `configurations[]
.nodes[].cpeMatch[]` (`criteria` CPE 2.3, `vulnerable`, `versionStartIncluding`,
`versionEndIncluding`, `versionEndExcluding`), `references[]` (`url`, `source`, `tags[]`). CPE 2.3
field order: `cpe:2.3:part:vendor:product:version:update:edition:language:sw_edition:target_sw:
target_hw:other` — Joomla extensions typically carry `target_sw = joomla`.

**CVE Program** — CVE Services API `https://cveawg.mitre.org/api/` (prod) and
`https://cveawg-test.mitre.org/api/` (test); auth headers `CVE-API-ORG`, `CVE-API-USER`,
`CVE-API-KEY`; endpoints `GET /cve/{id}` (public), `GET /cve-id?state=…&cve_id_year=…` (own ids),
`POST /cve-id` (reserve; `amount`, `cve_year`, `short_name`), `POST /cve/{id}/cna` (publish CNA
container), `PUT /cve/{id}/cna` (update), `POST /cve/{id}/reject`. Record format: CVE JSON 5.1.x;
5.2 introduced later (the brief's example says `5.2`). Schema: `CVEProject/cve-schema` on GitHub.
Public bulk data: `CVEProject/cvelistV5` — `cves/<year>/<NNNxxx>/CVE-<id>.json`, `cves/delta.json`
(latest hour), `cves/deltaLog.json` (recent history), plus release zips. Joomla CNA:
orgId `6ff30186-7fb7-4ad9-be33-533e7b05e586`, shortName `Joomla`; partner page
`https://www.cve.org/PartnerInformation/ListofPartners/partner/Joomla` (scope text to be copied
into `docs/feeds/cve.md`). Vulnogram: the CVE Program's open-source record editor; opens a full
record JSON.

**CWE** — `https://cwe.mitre.org/data/xml/cwec_latest.xml.zip`; views 699 (Software Development)
and 1425 (Top 25 2024) identify the practical subset.

**CVSS** — FIRST specifications 3.1 and 4.0; reference calculators are open source (BSD) and can be
vendored for the backend form.

## Appendix E — Glossary

| Term | Meaning |
|---|---|
| **Advisory** | One vulnerability, possibly affecting several products of one vendor; the unit of publication and of a CVE. |
| **Report** | One submission by one person (vulnerability or fix notice); never public. |
| **Case** | The communication around an advisory/report: participants, messages, attachments. |
| **Participant** | A person in a case with a role and a token-based access path. |
| **CNA** | CVE Numbering Authority; Joomla is one. |
| **CVE Record 5.x** | The JSON format the CVE Program accepts and publishes. |
| **Identity** | The Joomla tuple `type/element/folder/client_id` that names an installed extension. |
| **Live / Patched / Abandoned** | Public status of an advisory: no fix yet / fix available / vendor gone. |
| **Draft / Ready / Published / Retired** | Editorial state of an advisory. |
