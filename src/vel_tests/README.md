# Tests

| Suite | Where | Runs against | Needs | Run |
|---|---|---|---|---|
| Unit tests (mappers, CVSS, versions, mail parsing, …) | `unit/` | PHP only | PHP 8.3, PHPUnit 10 | `vendor/bin/phpunit -c vel_tests/phpunit.xml.dist` |
| **Feed tests** (the VEL feed runner) | `feeds/` | PHP + SQLite, fake cvelistV5 / NVD servers | PHP 8.3 + pdo_sqlite, PHPUnit 10, a Joomla 5/6 `libraries` folder | `vendor/bin/phpunit -c vel_tests/phpunit-feeds.xml.dist` |
| **Browser tests** (the Feed Runs page script) | `js/` | Node + jsdom | Node 18+ | `cd vel_tests/js && npm install && npm test` |
| **Cypress** (the Feed Runs page on a real site) | `cypress/` | your Joomla site, in a real browser | Node 18+, a site with com_vel, an admin login | `cd vel_tests/cypress && npm install && npx cypress open` |

The first three need no Joomla site and make no network requests. Cypress is the one to use
when something works in the tests but not on a site: it drives the real page and shows every
request the page makes and every request the server makes to the feeds.

Run PHP commands from the repository root (the folder that holds `administrator/`, `media/` and
`vel_tests/`).

---

## Feed tests — `feeds/`

The real `FeedRunner`, feed sources, HTTP clients, delta-log parser and queue, run against an
SQLite copy of the feed tables and fake cvelistV5 / NVD servers built from `fixtures/vel`. The
few CMS services the feed code touches (Text, Log, ComponentHelper, Factory, HttpFactory) and
the advisory side (VelService, AdvisoryMerger) are replaced by `feeds/Support/CmsStubs.php` —
which is why this suite has its own configuration and must not share a process with the others.

Joomla's Database, Registry and Http packages come from a Joomla 5 or 6 `libraries` folder. The
bootstrap looks, in order, at `JOOMLA_LIBRARIES`, `JOOMLA_ROOT/libraries`, and `libraries/` in
this repository. Without one (or without pdo_sqlite) every test is skipped and says why.

```bash
vendor/bin/phpunit -c vel_tests/phpunit-feeds.xml.dist
JOOMLA_LIBRARIES=/var/www/joomla/libraries vendor/bin/phpunit -c vel_tests/phpunit-feeds.xml.dist
vendor/bin/phpunit -c vel_tests/phpunit-feeds.xml.dist --filter FeedLossTest
```

No PHPUnit in `vendor/`? `composer require --dev phpunit/phpunit:^10.5` in the repository root, or
download the PHPUnit 10 phar and run `php phpunit.phar -c vel_tests/phpunit-feeds.xml.dist`.

It takes about two minutes: the CVE client deliberately pauses 0.2 s between record fetches, and
the NVD client 1 s between requests, exactly as on a live site.

| File | What it shows |
|---|---|
| `DeltaLogParserTest` | The delta log read in slices of any size gives the same entries as the whole file; a log that did not load (HTML page, corrupt or cut-off entry, garbage) throws instead of looking empty. |
| `FeedRunnerStepsTest` | A run in steps: `start()` makes no request; each step stops at its budget with 30 s HTTP timeouts; pressing Run twice attaches to the same run; one request at a time per run (the lock, and taking over a dead one); Stop; abandoned runs; NVD paging, enrichment and refusals; the NVD pause kept across steps; the seed; an out-of-date schema; what each step reports to the page, including every outbound request. |
| `FeedLossTest` | Nothing is lost: a log that does not load (500, HTML, corrupt, cut off, changing while read) changes nothing; a read stopped or interrupted changes nothing; a 340 KB log read in 64 KB slices queues every id once; a log that changes mid-read is read again; runs cut short by the fetch cap store, over the next runs, exactly what one uncapped run stores — with the log standing still or moving on, one call per run or step by step; the position is exact (no overlap, no gap, late entries caught); failing records are retried, then set aside; upgrading from the old cursor; a feed off for 40 days. |

---

## Browser tests — `js/`

`media/com_vel/js/feedrun.js` in jsdom, on the page as `tmpl/feedruns/default.php` renders it,
against a scripted server answering with the reports the real `FeedRunner` produced, and with
failures: a gateway timeout (504), a 500, the network gone (gives up after 5 retries, then
Continue), a busy run, an expired session (login page), no permission (403), Stop during and
between steps, the waiting note when the script does not start, a start-up error.

```bash
cd vel_tests/js
npm install
npm test
```

The page and the reports are in `js/fixtures/feedrun-page.json`, written from the real code by
`php vel_tests/js/build-fixtures.php` (same `libraries` lookup as the feed tests). Run it again after
changing the template, the runner or the language strings; it also writes
`cypress/fixtures/feedrun-cna.json`.

---

## Cypress — `cypress/`

Drives the Feed Runs page of a real Joomla site in a real browser.

### Set up

1. A Joomla site with com_vel installed **from this code** (see the check-list below), reachable
   from the machine that runs Cypress. A development copy of the site, not production: the live
   spec runs the feeds for real.
2. An administrator account with the VEL `vel.feeds` permission (any Super User) and **no
   two-factor login** (Cypress types the password into the normal login form).
3. Node 18 or later, then:

```bash
cd vel_tests/cypress
npm install
cp cypress.env.example.json cypress.env.json     # then put the login in it
```

### Run

```bash
# Interactive: pick a spec, watch the page, click any step in the command log to see the
# request and response (Cypress lists every XHR/fetch the page makes).
CYPRESS_BASE_URL=https://jed.localhost npx cypress open

# Headless, in the terminal:
CYPRESS_BASE_URL=https://jed.localhost npm run test:setup   # is it set up? (run this first)
CYPRESS_BASE_URL=https://jed.localhost npm run test:ui      # the panel, steps stubbed
CYPRESS_BASE_URL=https://jed.localhost npm run test:live    # real CVE and NVD runs to the end
```

(Windows PowerShell: `$env:CYPRESS_BASE_URL="https://jed.localhost"; npm run test:setup`.)

| Spec | What it does | Changes data? |
|---|---|---|
| `e2e/feedrun-setup.cy.js` | The check-list below, automated: the script and its asset entry are on the server; pressing Run opens the progress page (fails with Joomla's message if not — e.g. the schema); the script starts; one **real** step of each feed — printing the requests the server made to GitHub / the NVD and their answers — then Stop. | A feed run row each, closed as "partial, nothing was changed". |
| `e2e/feedrun-ui.cy.js` | The panel with the steps answered from `fixtures/feedrun-cna.json`: every step shown, the result, the request list; Stop (real server); a 504 retried; the script blocked; an expired session. No feed is fetched. | Runs started and stopped (nothing changed). |
| `e2e/feedrun-live.cy.js` | Presses Run CVE, then Run NVD, and follows every step to the end, printing each report. | Yes — exactly what pressing Run does. |

What `test:setup` / `test:live` print in the terminal, for each step:

```
cna step 1 [records] Fetching queued CVE records: 0 of 400 this run (3120 queued in all) | Running | seen 0, new 0, updated 0
    GET https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/deltaLog.json [bytes=0-2097151] -> 206, 2097152 bytes, 412 ms
cna step 2 [records] Fetching queued CVE records: 24 of 400 this run (3120 queued in all) | Running | seen 1, new 1, updated 0
    GET https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/2026/105xxx/CVE-2026-105606.json -> 200, 5120 bytes, 180 ms
    ...
    stored CVE-2026-105606 (new) Joomla Extension - ...
```

These specs were written against Joomla's standard admin markup (login form, Atum toolbar and
message container) and the Feed Runs template; they could not be run where they were written (no
Joomla site or Cypress there), so a first run may need a selector adjusted for your template.

---

## Pressing Run shows nothing: check-list

`feedrun-setup.cy.js` checks all of this; by hand:

1. **The media files are on the server.** `media/com_vel/js/feedrun.js` and the updated
   `media/com_vel/joomla.asset.json` live in the site's top-level `media/` folder, not under
   `administrator/components/com_vel`. Open `https://<site>/media/com_vel/js/feedrun.js` in the
   browser: it must show the script. If the file is missing Joomla silently leaves the script out,
   and the panel is drawn but never moves — the page now says so in red.
2. **The database has been updated.** `sql/updates/mysql/4.0.1.sql` adds `progress` and
   `heartbeat` to `#__vel_feed_runs` and creates `#__vel_feed_backlog`. Updating the package does
   it; after copying files by hand use System → Maintenance → Database → com_vel → Update
   Structure, or run the file's statements with your table prefix for `#__`. Without it, pressing
   Run shows "The feed runs table is missing its progress columns".
3. **The script starts.** On the progress page the yellow "Waiting for the progress script…" note
   disappears within a second. If it stays: F12 → Console for an error, Network for
   `feedrun.js` (404? blocked?). A Content-Security-Policy that blocks the site's own scripts, or
   an old cached copy of the page, are the usual causes — reload with Ctrl+F5.
4. **The steps answer JSON.** F12 → Network, filter `feeditems.step`: each should be a 200 with a
   JSON body. A 200 that is an HTML page means the session ended; PHP warnings printed before
   the JSON (Global Configuration → Server → Error Reporting set to Maximum with display on)
   break it too — the panel says "not a progress report".
5. **The server can reach the feeds.** The activity log lists every request the server made:
   `GET cvelistV5/cves/deltaLog.json … → 206` is good; `→ failed: …` means the web server cannot
   reach `raw.githubusercontent.com` (or `services.nvd.nist.gov` for the NVD feed) — a firewall
   or proxy rule.
6. **Permissions.** Run needs the VEL `vel.feeds` permission (Super Users have it).

No component options are needed. An NVD API key (VEL Options) makes the NVD feed faster (1 s
between NVD requests instead of 6.5 s) but is not required.
