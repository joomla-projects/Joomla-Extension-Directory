# VEL 2 — implementation notes

`com_vel` is the Vulnerable Extension List rebuilt as its own component inside `pkg_jed`
(see `VEL2-PRD.md` at the repository root for the requirements and decisions D-1…D-10).

| Document | What it covers |
|---|---|
| [STATUS.md](STATUS.md) | Which PRD tasks are built, which need a Joomla install to verify, which are open |
| [admin-guide.md](admin-guide.md) | The VEL team's workflow end to end |
| [cve-workflow.md](cve-workflow.md) | CVE record conventions, generation, CVE Services |
| [api.md](api.md) | The public JSON v2 API, the check endpoint, the RSS feeds, the legacy feed |
| [feeds/VEL2-Feeds-PRD.md](feeds/VEL2-Feeds-PRD.md) | The feed subsystem's own PRD: what the live services actually do (R-01/R-02, measured 2026-11-05) and the amendments that followed |
| [feeds/cve.md](feeds/cve.md) | The CVE Program feed: cvelistV5 delta polling, seed, mapping, CVE Services, runbook |
| [feeds/nvd.md](feeds/nvd.md) | The NVD feed: API, rate limits, mapping, runbook |
| [feeds/RESEARCH-PROGRESS.md](feeds/RESEARCH-PROGRESS.md) | The research log behind the two feed documents; the scripts and corpus are in `.tmp-research/` |
| [migration/vel-legacy.md](migration/vel-legacy.md) | Legacy data (R-04), the report import (M-02), the advisory import (M-01), removing the old code (M-04) |
| [security-review.md](security-review.md) | The Q-02 checklist |

## Where the code is

```
administrator/components/com_vel/      backend: schema, services, editor, queues, feeds, CVE
components/com_vel/                    site: list, detail, forms, case page, API, legacy feed
media/com_vel/                         CSS
plugins/task/vel/                      vel.feed_cna, vel.feed_nvd, vel.mailfetch, vel.housekeeping
plugins/privacy/vel/                   export and erasure by account and by e-mail
plugins/actionlog/vel/                 publish/retire/status/CVE decisions in the User Actions Log
plugins/system/velcheck/               the reference consumer for any Joomla site (not in pkg_jed)
administrator/components/com_jed/…     TicketType::Vulnerability (11), VulnerabilityTicketHandler,
                                       block reason VL1, the listing badge hook
tests/unit/Vel/                        PHPUnit for the framework-free classes
```

## Installing for the first time

1. Build `pkg_jed` as usual; `com_vel` and its three plugins are in `pkg_jed.xml`.
2. Enable the three plugins and create scheduled tasks for the four `vel.*` routines
   (suggested: feed_cna hourly, feed_nvd every 4 hours, mailfetch every 5 minutes if IMAP is
   used, housekeeping daily).
3. In *VEL › Options*: disclosure policy, the public contact address and PGP key, the CNA details,
   the CVE Services credentials (test environment first), the NVD API key, the from address.
   Then, once, *Feed runs › Seed CNA Back Catalogue* — repeat until the message says complete —
   so the hourly delta poll starts from a full queue rather than from 29,000 strangers' records.
4. Run the sample data plugin `jed2` (or create the menu items by hand): the advisories list must
   sit at `/vulnerable-extensions` with the report and fix-notice items under it.
5. Give the VEL team group `core.manage`, `core.create`, `core.edit`, `core.edit.state`,
   `vel.communicate`, `vel.feeds`, `vel.jed` on com_vel; the lead also `vel.publish` and `vel.cve`.
6. Optional: drop the CVE JSON schema (`schema/CVE_Record_Format.json` from the `v5.2.0` tag of
   `CVEProject/cve-schema`, CC0, draft-07, one self-contained file) into
   `administrator/components/com_vel/schema/cve-record.json` and `composer require opis/json-schema`
   in the component folder for full schema validation; `composer require webklex/php-imap` there
   for IMAP polling.

## Running the tests

```
composer require --dev phpunit/phpunit   # at the repository root, once
vendor/bin/phpunit -c tests/phpunit.xml.dist
```

The unit tests need no Joomla installation and no database: they cover CVSS scoring, version
matching, the CVE record builder and validator against the brief's example, the feed mappers and
classifier (on hand-made records and on the live-captured fixtures in `tests/fixtures/vel/`),
the delta-log head parser, and inbound mail parsing.

## Language strings

Everything a person can read goes through a language key - redirect messages, run messages
stored on `#__vel_feed_runs`, advisory notes, exception messages that surface in the
administrator, API error bodies, form hints and placeholders. Two classes are deliberately
different because they run without Joomla (the unit tests load no framework):

- The CVSS parsers throw `CvssException`, whose message *is* the key and whose `args` are the
  `sprintf` arguments; `AdvisoryModel` renders it with `Text::sprintf($e->getMessage(), ...$e->args)`.
- `CveRecordValidator` returns problems as `[key, ...arguments]` arrays; the CVE preview and the
  submit action render them with `Text::sprintf(...$problem)`.

Three sets of strings are English on purpose and must stay that way: the content of generated
CVE records (`lang: en`, read by the CVE Program), the legacy feed's field vocabulary
(`Live VEL`, `Very High`, `SQL Injection` - a consumer contract), and `Log::add()` lines,
which are diagnostics for the server log rather than text for a person on the site.
