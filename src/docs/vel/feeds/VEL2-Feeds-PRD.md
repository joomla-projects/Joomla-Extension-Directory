# VEL 2 — Inbound feeds subsystem PRD

**Status:** draft for review. **Date:** 2026-11-05. **Supersedes:** VEL2-PRD §6.6 (the parent PRD
should be amended as set out in §9 below).

This document specifies the two inbound feeds — the CVE Program feed (F-02) and the NVD feed
(F-03) — on the basis of research tasks **R-01** and **R-02**, both now complete. Every external
fact here was measured against the live service on 2026-11-05, not recalled. The raw
measurements are in [`RESEARCH-PROGRESS.md`](RESEARCH-PROGRESS.md); the operator-facing
documents are [`cve.md`](cve.md) and [`nvd.md`](nvd.md).

---

## 1. Why this document exists

The parent PRD's §6.6 was written by an instance with no network access. It marked every external
fact `[VERIFY R-01]` / `[VERIFY R-02]` and asked that the markers be resolved before the parser
tasks start. They now are. Most of the design survives contact with the real services, but
**eight assumptions do not**, and three of those change the architecture rather than a constant.
This document restates the feed design with those corrections folded in, so F-01…F-04 can be
built from one consistent source.

### 1.1 What changed, at a glance

| # | Assumption in VEL2-PRD §6.6 / Appendix D | Reality | Impact |
|---|---|---|---|
| 1 | `deltaLog.json` is a modest hourly log | **37 MB**, updated every ~11 min | **Architectural** — §3.2 |
| 2 | `GET /api/cve?assigner_short_name=…` is an alternative source | **403** without the Secretariat / Bulk Download role | **Architectural** — §4.2 |
| 3 | CPE identifies Joomla core vs extension | No CPE at all on 2024–2025 records | **Architectural** — §5 |
| 4 | Prefer NVD's *Primary* CVSS | Would discard **all 375** CVSS 4.0 scores | Significant — §6.2 |
| 5 | A GitHub token gives rate-limit headroom | Raw GitHub is not the API; a token buys nothing | Drop an option — §3.4 |
| 6 | NVD reference tags are the CVE 5.x vocabulary | NVD uses its own Title Case set | Mapping table — §6.4 |
| 7 | The CVE schema is MIT | **CC0-1.0**, draft-07 | Validator choice — §7 |
| 8 | `cve_data_version` is 5.1 *or* 5.2, undecided | Emit `5.2`; **parse both**; irrelevant to submission | Resolves §10 Q3 — §7 |

And one thing nobody knew to ask about: **the Joomla CNA already runs an extension-CVE programme
at scale, with a machine-readable house style.** That is §2, and it is the most consequential
finding in the research.

---

## 2. The thing the parent PRD did not know

VEL 2 is being designed as though the Joomla CNA's extension-CVE work is ahead of us. It is not.
Records published by `security@joomla.org`, by year:

```
2020: 7    2021: 24   2022: 16   2023: 24   2024: 6    2025: 43   2026: 326
```

In 2026 the CNA went from roughly two records a month to thirty, covering **43 distinct vendor
domains**. 315 of those 326 records follow one of two strict title templates:

```
Joomla Extension - <vendor-domain> - <summary> in <product> <version range>     (289)
Joomla! Core - [YYYYMMDD] - <summary> in Joomla <version range>                  (26)
```

### 2.1 Consequences for the product

- **The feed is not seeding an empty system.** The first full run will import ~326 records for
  2026 alone, plus whatever the look-back covers. First-run behaviour, paging and the
  auto-create option need to be sized for hundreds of items, not dozens. See §3.5.
- **The title templates are the best classifier signal available**, better than CPE and better
  than description keyword matching, because they are *ours* and they are regular. See §5.
- **The `title_prefix` and `product_suffix` options in VEL2-PRD §6.3.3 are already correct** —
  `Joomla Extension - {vendor} - ` and ` extension for Joomla` are exactly what the CNA emits.
  That is a strong validation of the editorial model and should not be changed.
- **`x_generator.engine` is `Vulnogram 0.1.0-dev`** on every sampled record. The team works in
  Vulnogram today. VEL2-PRD §10 Q4 asks whether the generator should optimise for "open in
  Vulnogram" or direct API submission; the evidence says the full-record download must be
  first-class from day one, because it is the existing workflow, and direct submission is the
  improvement being offered. **Recommendation: full record is the toolbar default; direct
  submission is the second button.**

### 2.2 Consequences for Appendix C (the record builder)

Three prescriptions in VEL2-PRD Appendix C are not what the CNA's own records contain:

| Appendix C says | The CNA actually emits |
|---|---|
| `collectionURL: "https://extensions.joomla.org"` | **absent** |
| `platforms: ["Joomla"]` | **absent** |
| `packageName: <element>` | **absent** |
| `vendor` = a display name | the vendor's **domain** (`joomlaboat.com`) |
| `product` = `product` + `product_suffix` | ✔ confirmed (`Custom Tables extension for Joomla`) |
| `descriptions[0].value` = title + `" - "` + text, HTML in `supportingMedia` | ✔ **confirmed in production** |
| `cveMetadata.assignerShortName` = `Joomla` | ✔ confirmed |
| core vendor = `Joomla` | **`Joomla! Project`** |

`collectionURL` and `platforms` are valid CVE 5.x and arguably *improve* the record. But emitting
fields the CNA has never emitted is a change of editorial practice, not a technical decision.
**Recommendation: keep them, default them off, and raise them with the VEL team as a deliberate
improvement** — not as an invisible side effect of the new tool. `packageName` should be emitted
whenever the advisory has a Joomla `element`, because it is genuinely new information the
hand-written records lack.

The core record additionally carries **`impacts[]`** (`{capecId, descriptions}`), a container
Appendix C does not list. Add it as optional.

---

## 3. CVE Program feed (F-02)

### 3.1 Source decision

`CVEProject/cvelistV5` over raw HTTPS, unauthenticated. **This is the only source.** The
"alternative" CVE Services list in VEL2-PRD §6.6.1 is not available to us (§4.2).

### 3.2 `deltaLog.json` via HTTP Range — the architectural change

Measured:

| Fact | Value |
|---|---|
| Size | **37,164,365 bytes** |
| Retention | rolling **30.00 days**, newest first |
| Update cadence | **every ~11 min** (p50), not hourly; p90 30.4 min, max 228.8 min |
| Volume | ~4,130 changed records/day; avg 45 per entry, max 1,914 |
| `Accept-Ranges` | **bytes** ✔ |
| `Cache-Control` | `max-age=300`, `ETag` present |

Downloading 37 MB hourly is 888 MB/day for a feed whose useful payload is a few dozen CVE ids.
It is also pointless: the array is **newest-first and pretty-printed**, so the newest entries are
the *first* bytes of the file.

**`CvelistClient` therefore:**

1. Sends `If-None-Match` with the stored `ETag`. A `304` ends the run as a clean no-op.
2. Otherwise sends `Range: bytes=0-<n>`, where `n` is sized from the gap since the last
   successful run at ~13.6 KB/entry and ~5 entries/hour, clamped to `[64 KB, cna_range_max_kb]`
   (default 2 MB ≈ 28 hours of history).
3. Expects **206**. Parses forward to the last *complete* top-level entry and discards the
   partial tail.
4. Stops reading once it reaches an entry whose `fetchTime` is older than the last run's
   `window_to`.
5. **Escalates** if the oldest entry in the range is still newer than `window_to` — the window
   was under-sized. It doubles the range and retries, up to the cap, then records the run as
   `partial` with a message and leaves `window_to` unmoved so the next run retries. It never
   silently skips.
6. **Falls back** to a full unranged GET only if the server answers `200` to a `Range` request
   (i.e. ignores it) *and* the body is within `cna_full_fetch_max_mb` (default 64).

A gap longer than 30 days cannot be served by the delta log at all. In that case the run is
marked `partial` with a message directing the operator to a backfill, and `window_to` advances
only to the oldest available entry.

⚠ **`delta.json` must not be used as the feed source.** It is 980 bytes and contains only the
single newest ~11-minute window — an hourly poll of it would silently lose about 80% of changes.
It is retained as a cheap liveness probe for the *Run now* button's pre-flight check.

### 3.3 Fetching records

Each delta item carries **`githubLink`** — the complete raw URL — so the bucket arithmetic is not
needed. The layout is confirmed anyway as a fallback:
`cves/<year>/<floor(sequence/1000)>xxx/CVE-<year>-<sequence>.json`, bucket **not** zero-padded,
6-digit sequences work.

Per-run fetch cap `cna_max_records_per_run` (default 400). Hitting it marks the run `partial`
and leaves `window_to` at the oldest unprocessed entry so the next run resumes exactly there.
Records are fetched newest-first so that a capped run still delivers the most relevant items.

### 3.4 Rate limiting — and the option to delete

`raw.githubusercontent.com` is served by Fastly, returns **no `X-RateLimit-*` headers**, and is
not governed by the GitHub REST API quota. The measured 60/hr unauthenticated API limit is
irrelevant to us because we never call the API.

⇒ **Remove the `GitHub token` option from VEL2-PRD §6.3.3.** It buys nothing and invites an
operator to store a credential for no benefit. Raw GitHub has an undocumented abuse threshold;
the mitigations are the Range request, the per-run cap, and a 200 ms courtesy delay between
record fetches — not a token.

### 3.5 First run

With `cna_lookback_days = 7` the first run sees roughly 29,000 changed records, of which a
handful are Joomla's. That is 29,000 record fetches — unacceptable.

**The first run must not use the delta log.** Instead, `CnaFeedSource` seeds from
`GET /cve-id` (§4.2) for our own records, and from a one-off scan of the repository's
`cves/<year>/` listings for other CNAs' Joomla records, both bounded by `cna_seed_years`
(default: the current year and the previous one). This is a separate, explicitly-invoked
*Seed* action in the Feed runs view, not something a cron job does by surprise. After the seed,
`window_to` is set to now and ordinary delta polling takes over.

This is new scope that VEL2-PRD does not budget for. It belongs in F-02 and adds roughly two days.

### 3.6 Record shapes the parser must survive

| Shape | Fixture | What breaks a naive parser |
|---|---|---|
| Current house style | `cve-joomla-cna-extension.json` | — (the happy path) |
| Core | `cve-joomla-cna-core.json` | vendor `Joomla! Project`; `impacts[]` |
| Pre-2026 Joomla | `cve-joomla-cna-legacy.json` | `problemTypes[].descriptions[].type = "text"` with **no `cweId`**; `x_refsource_MISC` tags |
| Another CNA | `cve-other-cna-extension.json` | **`dataVersion: "5.1"`** |
| Rejected | `cve-rejected.json` | `containers` = `[cna]` only; `cna` has only `providerMetadata`, `rejectedReasons`, `x_generator`; **no `descriptions`, no `affected`**; `cveMetadata.dateRejected` |

**`dataVersion` tracks the assigner's last write, not the repository.** Records untouched since
~2025 are still `5.1`; everything written in 2026 is `5.2`. The parser accepts
`^5\.\d+(\.\d+)?$` and must not branch on the exact value.

### 3.7 ADP containers

Both 2026 fixtures carry exactly one ADP container: `shortName: "CISA-ADP"`, keys `metrics`,
`title`, `providerMetadata`, with `metrics[0].other.type = "ssvc"`. **No CVSS, no
`problemTypes`.**

VEL2-PRD §6.6.1 says ADP containers "are read for CVSS/CWE when the CNA container has none".
That is correct as a rule but will rarely fire for Joomla records, because our CNA always
supplies its own CVSS — CISA Vulnrichment fills gaps, and we leave none. Keep the fallback;
do not present it to the editor as a likely source. The SSVC block is ignored for now (stored in
`payload`, not mapped).

---

## 4. CVE Services API

### 4.1 What is confirmed

Verified against `CVEProject/cve-services` `api-docs/openapi.json` — OpenAPI 3.0.2, **API 2.9.2**.

- Base URLs `https://cveawg.mitre.org/api/` (prod) and `https://cveawg-test.mitre.org/api/`
  (test, separate dataset). Both live.
- Auth headers `CVE-API-ORG`, `CVE-API-USER`, `CVE-API-KEY`, required per-operation. Confirmed
  live by the 400 response naming `CVE-API-ORG`.
- `GET /cve/{id}` is **public and unauthenticated**.
- `POST`/`PUT /cve/{id}/cna` take `{"cnaContainer": …}`. Minimum viable container is
  `descriptions[]` + `affected[]` + `references[]`.

### 4.2 ⚠ `GET /cve` is not available to us — remove the alternative source

The specification's access note on `GET /cve` and `GET /cve_cursor`:

> "User must belong to an organization with the **Secretariat** or **Bulk Download** role."

An ordinary CNA has neither. **VEL2-PRD §6.6.1's "Alternative: CVE Services
`GET /api/cve?assigner_short_name=Joomla&time_modified.gt=…`" does not exist**, and the
`cna_source` option's `cve_services` value must be removed — leaving `cvelist_github` as the only
source, i.e. the option itself can go.

The correct path to "our own records", used by the seed in §3.5 and by a *Reconcile* action:

```
GET /cve-id?state=PUBLISHED&cve_id_year=2026&page=N      (authenticated, "registered users")
  → {totalCount, itemsPerPage: 500, pageCount, currentPage, prevPage, nextPage, cve_ids[]}
then for each id:
GET /cve/{id}                                            (public, no auth)
```

### 4.3 Reserve — one parameter the parent PRD misses

`POST /cve-id`, all parameters in the **query string**:

| Param | Required | Note |
|---|---|---|
| `amount` | ✔ | |
| `cve_year` | ✔ | 1999 … currentYear + 1 |
| `short_name` | ✔ | |
| `batch_type` | **when `amount` > 1** | `sequential` / `non-sequential` / `nonsequential` — ⚠ **not in VEL2-PRD** |

**HTTP 206 is a success-with-partial-fulfilment** (the id quota ran out mid-request) and must be
handled distinctly: store what was returned, warn the editor, do not retry blindly.

### 4.4 Submit — four refinements

- ⚠ `providerMetadata`, `datePublished` and `assignerShortName` are **server-set and overwritten
  if supplied**. Appendix C tells the builder to emit `providerMetadata`; harmless, but it should
  be stripped from the *CNA container* shape and kept only in the *full record* shape used for
  download and Vulnogram.
- Optional query param **`erlcheck`** (boolean) runs stricter validation against the CVE
  Program's enrichment-data requirements. Expose it as a `cve_erlcheck` option, default on for
  the test environment.
- The 200 body is `{valid, warnings: [], message, created}` — **surface `warnings`** in the UI.
  A submission can succeed and still tell us something.
- A submission body is a bare container with **no `dataVersion` field**, so `cve_data_version`
  never affects what we submit.

### 4.5 Worth adding

`GET /org/{shortname}/id_quota` — check the remaining id quota before reserving, and show it as a
dashboard tile. Cheap, and it turns a confusing 206 into a predictable one.

---

## 5. Classification — redesigned

VEL2-PRD §6.6.2 classifies on CPE. **That cannot work on modern records.** Records carrying any
CPE with vendor `joomla`, by publication year:

```
2005:4 2006:53 2007:58 2008:117 2009:143 2010:210 2011:73 2012:43 2013:15
2014:14 2015:13 2016:6 2017:19 2018:24 2019:27 2020:39 2021:28 2022:13
2023:5  2024:0  2025:0  2026:27
```

Zero in 2024 and 2025. Of the 326 Joomla CNA records published in 2026, only **94** have any
`configurations`; 209 are `Deferred`, meaning NVD has stopped enriching them. A CPE-led rule
fires reliably only on records from the Bush administration.

Two further CPE corrections, both measured:

- Core is spelled **two ways**: product `joomla\!` (3,301 rows) and product `joomla`
  (622 rows). Matching only the first misses 622 rows.
- CPE vendor `joomla` is **not** proof of core: `rs_gallery2` (41), `com_yvcomment` (16),
  `bsq_sitestats` (8), `com_user` (6), `com_eventing` (6), `ignitegallery` (4) all sit under that
  vendor and are **extensions**. The product test is mandatory, not belt-and-braces.

### 5.1 The rule `RecordClassifier` implements

Evaluated in order; the first match wins, and the matched rule is stored on the feed item as
`classification_reason` so an editor can see *why*.

| # | Test | Result |
|---|---|---|
| 1 | `cveMetadata.state == "REJECTED"` (or NVD `vulnStatus == "Rejected"`) | `rejected` — stored, never an advisory; if it matches an existing advisory, flag it loudly |
| 2 | assigner is ours (`assignerShortName == "Joomla"` or `assignerOrgId` matches `cna_org_id`; NVD: `sourceIdentifier == security@joomla.org`) **and** any `affected[]` has vendor ∈ {`Joomla! Project`, `Joomla`} and product ∈ {`Joomla! CMS`, `Joomla!`, `Joomla`} | `joomla_core` |
| 3 | assigner is ours **and** title matches `/^Joomla!? Core\s*-\s*\[\d{8}\]/` | `joomla_core` |
| 4 | assigner is ours | `joomla_cna` (the default for our own records — title `/^Joomla Extension - /` raises confidence to *high*) |
| 5 | CPE vendor `joomla` **and** product ∈ {`joomla\!`, `joomla`} | `joomla_core` |
| 6 | any CPE `target_sw == joomla` | `joomla_extension_other_cna` (high confidence, low recall — 13 records corpus-wide) |
| 7 | `affected[].platforms` contains `Joomla`, or `affected[].collectionURL` is `extensions.joomla.org`, or product matches `/joomla/i` | `joomla_extension_other_cna` |
| 8 | description matches `/joomla/i` **and** `/(extension\|component\|module\|plugin\|template)/i` | `joomla_extension_other_cna`, confidence *low* |
| 9 | description matches `/joomla/i` only | `unclassified` — **queued for a human, not discarded** |
| 10 | otherwise | `unrelated` — not stored |

Rule 2 precedes rule 4 so a core record never becomes an extension draft. Rules 5–8 never apply
to our own records, so another CNA's mis-tagged CPE cannot reclassify something we assigned.

### 5.2 The canonical false positive

`nvd-unrelated-keyword-hit.json` is CVE-2024-32788, **"FG Joomla to WordPress"** — a *WordPress*
plugin whose product name contains "Joomla". It satisfies rule 8's first half and must not be
auto-created. It is the regression test for rule ordering. The corpus contains a family of these
(XCloner, FG PrestaShop to WooCommerce, WP-Cumulus) — at least twelve records whose only Joomla
connection is a name.

### 5.3 New schema requirement

`#__vel_feed_items` as specified in VEL2-PRD §6.1.9 cannot record any of this. Add:

```
classification_reason  varchar(32)   -- which rule fired
confidence             enum(high, medium, low)
```

and extend `classification` with **`rejected`** and **`unclassified`** (the enum currently has
`unknown`, which is doing two jobs). Extend `#__vel_feed_runs.status` with the already-used
`partial` — it is in the enum but §3.2/§3.3 now give it three distinct meanings, so
`#__vel_feed_runs.message` must be populated, not optional.

---

## 6. NVD feed (F-03)

### 6.1 ⚠ Error handling — the finding most likely to cause a silent bug

**NVD reports parameter errors as HTTP 404, with an empty body and the reason in a `message`
HTTP response header.**

| Request | Response |
|---|---|
| 153-day window | `404` + `message: Date range cannot exceed 120 days.` |
| `resultsPerPage=2001` | `404` + `message: resultsPerPage parameter cannot exceed 2000.` |
| `lastModStartDate` alone | `404` + `message: Both lastModStartDate and lastModEndDate are required when either is present.` |

`NvdClient` must read the `message` header on every non-200 and must distinguish
*404-with-message* (our bug — fail the run loudly) from *404-without-message* (no such CVE —
skip quietly). A client that treats 404 as "not found" will mis-handle every malformed request
and report a clean run.

### 6.2 ⚠ CVSS selection — "prefer Primary" is wrong

Measured over the full 1,633-record Joomla corpus:

| Container | Records | Primary | Secondary |
|---|---|---|---|
| `cvssMetricV2` | 1151 | 1146 | 5 |
| `ssvcV203` | 473 | — | — |
| `cvssMetricV31` | 405 | 259 | 164 |
| **`cvssMetricV40`** | **375** | **0** | **375** |
| `cvssMetricV30` | 199 | 194 | 5 |

`cvssMetricV40` is Secondary in **375 of 375** cases — NVD produces no v4.0 analysis of its own,
so all v4.0 comes from CNAs, including every 2026 Joomla record. VEL2-PRD §6.6.2's "Primary
first, then Secondary" would discard the lot.

**Selection rule: version first, then type.** `4.0 → 3.1 → 3.0`; ignore v2 entirely; within the
chosen version prefer `Primary` over `Secondary`. Store which container and which type won.

Two more: **`cvssMetricV30` (199 records) is not handled by the PRD at all** — map it, as above.
**`ssvcV203` (473 records) is not a CVSS block** — the parser must not iterate `metrics` assuming
every key is one.

### 6.3 CWE filtering

`weaknesses[].description[].value` is not always a CWE id:

| Value | Count |
|---|---|
| real `CWE-…` (Primary) | 1074 |
| real `CWE-…` (Secondary) | 517 |
| `NVD-CWE-Other` | 88 |
| `NVD-CWE-noinfo` | 53 |

The 141 `NVD-CWE-*` values are placeholders, not identifiers, and would fail
`#__vel_advisory_cwes.cwe_id` validation. Filter them explicitly.

### 6.4 ⚠ Reference tags need a mapping table

NVD uses its own Title Case vocabulary, not the CVE 5.x lowercase list that VEL2-PRD §6.1.5
defines. Complete for this corpus: `Exploit` (2368), `Vendor Advisory` (1565), `Third Party
Advisory` (1036), `VDB Entry` (640), `Patch` (341), `Product` (225), `Mailing List` (78),
`Broken Link` (58), `Release Notes` (51), `URL Repurposed` (32), `Issue Tracking` (28),
`US Government Resource` (8), `Not Applicable` (6), `Technical Description` (4),
`Permissions Required` (4), `Mitigation` (2).

All map onto the CVE 5.x vocabulary except **`VDB Entry`** and **`URL Repurposed`**, which have
no equivalent. **Policy: keep the reference, drop the unmappable tag.** The full table lives in
`NvdRecordMapper` and is mirrored in [`nvd.md`](nvd.md).

### 6.5 `virtualMatchString` — tested, rejected, closed

VEL2-PRD §6.6.2 asks whether `virtualMatchString` can express `target_sw = joomla` and replace
the keyword query. **Yes to the first, emphatically no to the second:**

| Query | `totalResults` |
|---|---|
| `virtualMatchString` `target_sw=joomla` | **13** |
| `virtualMatchString` `target_sw=wordpress` (control) | **16,254** |
| `virtualMatchString` vendor `joomla` | 1,002 |
| `keywordSearch=Joomla` | **1,633** |

The WordPress control proves the parameter works; the Joomla data simply does not exist. Adopting
it would cut recall from 1,633 to 13 — all of them `huge-it`, `jextn`, `chronoengine` and
`joomlaux`. **`keywordSearch=Joomla` stays.** `target_sw=joomla` is retained as classifier
rule 6. The control figure is recorded here so nobody re-opens the question.

### 6.6 `sourceIdentifier` — an unlisted parameter worth using

`?sourceIdentifier=security@joomla.org` is a supported query parameter (558 records NVD-wide,
446 within the Joomla keyword corpus) and is **not in VEL2-PRD's parameter list**. It is a
cleaner way to recognise our own records in discovery than matching by id, and it gives the
enrichment pass a cheap way to fetch only our own corpus.

### 6.7 Other confirmed parameters and limits

Date format is **lenient** — bare, `+00:00`, `Z` and `.000Z` all parse identically, with a
missing offset read as UTC. Emit `…Z`. Window limit **120 days**, enforced. `resultsPerPage` max
**2000**, enforced. Rate limits 5/30 s unauthenticated and 50/30 s with a key are the documented
figures and remain the design basis; observed enforcement is lenient, and **there are no
rate-limit headers of any kind**, so backoff is a fixed 6.5 s / 1 s sleep and a 403 trips the
run.

### 6.8 Expectation management

| Measure | Value |
|---|---|
| Joomla CNA records published 2026 | 326 |
| …with any `configurations` | **94** |
| …`Deferred` / `Awaiting Analysis` / `Analyzed` / `Modified` | 209 / 23 / 89 / 5 |
| Whole corpus with no `configurations` | 287 / 1,633 (17.6%) |
| `security@joomla.org` records with any `joomla:joomla*` CPE | 75 / 446 |

Enrichment will come back empty for most recent records. The *Feed items* view must say
"NVD has not enriched this record (`Deferred`)" rather than showing an empty merge offer, or
editors will conclude the feed is broken. **`Deferred` is a state VEL2-PRD does not mention and
is the modal status for our own records.**

---

## 7. Schema, validation and `cve_data_version`

`https://raw.githubusercontent.com/CVEProject/cve-schema/v5.2.0/schema/CVE_Record_Format.json`,
72,841 bytes, latest release **v5.2.0 (2025-10-29)**. Default branch is `main`, not `master`.

| Fact | Finding |
|---|---|
| `$schema` | **draft-07** |
| External `$ref`s | **none — self-contained**, so no bundling step |
| Definitions | 46, including `cnaPublishedContainer`, `cnaRejectedContainer`, `adpContainer` |
| Licence | ⚠ **CC0-1.0**, not MIT as VEL2-PRD §6.5 states |

**Validator: `opis/json-schema` 2.6.0** (Apache-2.0, `php: ^7.4 || ^8.0`), which implements
draft-06/07/2019-09/2020-12. `justinrainbow/json-schema` is draft-04-centric and is **not** a
viable alternative for draft-07 — VEL2-PRD §6.5 and §8.8 offer the two as interchangeable, and
they are not.

Because `cnaPublishedContainer` is a named definition, the CNA-container-only shape validates
against `#/definitions/cnaPublishedContainer` in the same file. **One bundled schema file serves
both shapes.**

**`cve_data_version` (VEL2-PRD §10 Q3) is resolved: default `5.2`.** The option's description
must say it affects **downloads only** — a CVE Services submission body is a bare container with
no `dataVersion` field, so the option cannot affect submission. The *parser* must accept
`5.1` and `5.2` alike, because the repository does not normalise historic records forward.

---

## 8. Options — the delta against VEL2-PRD §6.3.3

| Option | Change |
|---|---|
| `cna_source` | **Remove.** The only value is `cvelist_github`; `cve_services` is not available to a CNA (§4.2). |
| GitHub token | **Remove.** Buys nothing for raw fetches (§3.4). |
| `cve_data_version` | Default **`5.2`**; description amended to "affects downloaded records only". |
| `cna_range_max_kb` | **New**, default 2048. The `Range` cap (§3.2). |
| `cna_full_fetch_max_mb` | **New**, default 64. The fallback cap (§3.2). |
| `cna_max_records_per_run` | **New**, default 400. The per-run fetch cap (§3.3). |
| `cna_seed_years` | **New**, default current + previous. Bounds the one-off seed (§3.5). |
| `cve_erlcheck` | **New**, default on for `test`. Stricter submission validation (§4.4). |
| `title_prefix`, `product_suffix` | **Unchanged** — confirmed to match the CNA's live output. |
| `cna_org_id` | **Unchanged** — `6ff30186-7fb7-4ad9-be33-533e7b05e586` confirmed live. |
| `nvd_api_key`, `nvd_lookback_days` | **Unchanged.** |

---

## 9. Amendments required to VEL2-PRD

| Section | Amendment |
|---|---|
| §6.3.3 | Options per §8 above |
| §6.1.9 | `#__vel_feed_items`: add `classification_reason`, `confidence`; extend `classification` with `rejected`, `unclassified` (§5.3) |
| §6.5 | Schema licence **CC0-1.0**, not MIT; validator is `opis/json-schema` specifically, not "or `justinrainbow`" |
| §6.6.1 | Delete the CVE Services "Alternative"; replace the delta description with §3.2; core vendor is `Joomla! Project`; add the seed run (§3.5); soften the ADP claim (§3.7) |
| §6.6.2 | Replace the CPE-led classification with §5.1; replace "Primary first" with version-first (§6.2); add the `message`-header error rule (§6.1); add the tag mapping (§6.4); resolve the `virtualMatchString` marker as *tested, rejected* (§6.5); add `sourceIdentifier` (§6.6); add `Deferred` |
| §8.8 | `opis/json-schema` named outright |
| §9 R-01 / R-02 | Mark complete; fixtures are at `tests/fixtures/vel/{nvd,cve}/`, 12 files, not `tests/fixtures/` |
| §9 F-02 | +2 days for the seed run (§3.5) |
| §10 Q3 | **Answered** — `5.2`, and it does not apply to submission |
| §10 Q4 | **Evidence supplied** — the CNA uses Vulnogram today; recommend full record as the default |
| §10 Q7 (core CVEs) | **Evidence supplied** — core is cheap to identify (title template + vendor `Joomla! Project`) and is only ~26 records/year, so a read-only mirror is inexpensive |
| §10 Q8 (other CNAs) | **Evidence supplied** — 79 non-Joomla-CNA Joomla records since 2024, dominated by VulnCheck (69), then CERT.PL, VulDB, Patchstack, INCIBE. Low enough volume to import and mark |
| Appendix C | `collectionURL`/`platforms` are not current CNA practice — default off (§2.2); add optional `impacts[]`; strip `providerMetadata` from the container shape (§4.4); core vendor `Joomla! Project` |
| Appendix D | Replace wholesale with the measured figures in [`cve.md`](cve.md) and [`nvd.md`](nvd.md) |

---

## 10. Open questions this research raises

1. **Seed strategy (§3.5).** Confirm the one-off seed is acceptable scope for F-02, and whether
   `cna_seed_years` of two years is enough — 2020–2025 adds only ~120 records, so "all years"
   is also affordable.
2. **`collectionURL` / `platforms` / `packageName` (§2.2).** Emitting these would be an
   improvement on the CNA's current records but is an editorial-practice change. VEL team to
   decide; recommend defaulting off and raising it separately.
3. **Core mirror (§10 Q7 above).** Now quantified at ~26 records/year and trivially identifiable.
   Still a product decision, but the cost argument against it has gone.
4. **`erlcheck` in production.** Default on for `test` is safe. Whether to default it on for
   `prod` depends on whether the CNA wants to meet the enrichment-data bar on every submission.
5. **SSVC.** 473 corpus records carry `ssvcV203`, and CISA-ADP supplies SSVC on ours. We store it
   in `payload` and ignore it. Worth surfacing later as a prioritisation signal?

---

## 11. Verification status

| Item | Status |
|---|---|
| R-01.1 endpoint + parameters | ✔ confirmed, `sourceIdentifier` added |
| R-01.2 date formats | ✔ confirmed, lenient |
| R-01.3 120-day window | ✔ confirmed; error is 404 + `message` header |
| R-01.4 `resultsPerPage` max 2000 | ✔ confirmed |
| R-01.5 rate limits | ✔ documented figures confirmed; no headers exist |
| R-01.6 `virtualMatchString` | ✔ answered — works, rejected, control measured |
| R-01.7 `sourceIdentifier` | ✔ `security@joomla.org`, 558 records |
| R-01.8 response shape | ✔ census of 1,633 records; 6 corrections |
| R-01.9 fixtures | ✔ 5 files |
| R-02.1 `deltaLog.json` | ✔ confirmed; 3 corrections + the Range finding |
| R-02.2 record URL layout | ✔ confirmed |
| R-02.3 GitHub rate limits | ✔ corrected — assumption was wrong |
| R-02.4 CVE Services | ✔ against OpenAPI 2.9.2; 1 major correction |
| R-02.5 record format version | ✔ resolved — `5.2`, parse both, irrelevant to submission |
| R-02.6 JSON schema | ✔ confirmed; licence corrected to CC0-1.0 |
| R-02.7 CNA scope statement | ✔ verbatim |
| R-02.8 fixtures | ✔ 7 files |

**R-01 and R-02 are complete. §6.6's `[VERIFY]` markers can be cleared on the strength of this
document.**
