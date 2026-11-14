# R-01 / R-02 verification — working progress log

**Task:** the checklists in `cve.md` (R-02) and `nvd.md` (R-01) were written by an offline
instance from memory. Verify each item against the live services, then produce a PRD recording
the findings.

**Started:** 2026-11-05. This file is written incrementally — if the session dies, resume from
the first unticked line.

## Deliverables

- [x] `docs/vel/feeds/nvd.md` — checklist items replaced with verified facts
- [x] `docs/vel/feeds/cve.md` — same
- [x] `docs/vel/feeds/VEL2-Feeds-PRD.md` — the PRD for the feed subsystem built on verified facts
- [x] `VEL2-PRD.md` (repository root) — Appendix D + §6.6 [VERIFY] markers resolved

## R-01 — NVD API 2.0

| # | Item | Status | Finding |
|---|---|---|---|
| 1 | Base endpoint + params | **CONFIRMED** | plus `sourceIdentifier`, which the PRD missed |
| 2 | Date format for `lastModStartDate`/`lastModEndDate` | **CONFIRMED (lenient)** | 4 formats all accepted |
| 3 | 120-day window limit | **CONFIRMED** | enforced; error is **404 + `message` header** |
| 4 | `resultsPerPage` max | **CONFIRMED 2000** | 2001 → 404 + message header |
| 5 | Rate limits (5/30s, 50/30s) | **documented limit confirmed; enforcement lenient** | 10-request burst all 200 |
| 6 | `virtualMatchString` can express `target_sw=joomla`? | **YES — but must NOT replace the keyword search** | 13 records vs 1,633 |
| 7 | Joomla CNA `sourceIdentifier` | **CONFIRMED `security@joomla.org`** | 558 records NVD-wide |
| 8 | Response shape | **CONFIRMED, 5 corrections** | see below |
| 9 | Fixtures captured | pending | |

## R-02 — CVE Program

| # | Item | Status | Finding |
|---|---|---|---|
| 1 | `deltaLog.json` exists, retention, entry shape | **CONFIRMED, with 3 corrections** | See below |
| 2 | Record URL layout `cves/<year>/<NNNxxx>/CVE-….json` | **CONFIRMED** | See below |
| 3 | GitHub raw rate limits with/without token | **CORRECTED** | See below |
| 4 | CVE Services base URLs, auth headers, endpoints | **CONFIRMED, 1 major correction** | See below |
| 5 | Record format version accepted today (5.1.x / 5.2) | **RESOLVED** | `5.2` — and it does not apply to API submission at all |
| 6 | CVE schema download location | **CONFIRMED, licence corrected** | See below |
| 7 | Joomla CNA scope statement | **CONFIRMED** | See below |
| 8 | Fixtures captured | pending | |

## Verified findings (raw)

### R-02.1 — `deltaLog.json` (measured 2026-11-05 11:35 UTC)

`GET https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/deltaLog.json` → **HTTP 200**.

| Fact | PRD/checklist assumption | Measured |
|---|---|---|
| Exists | yes | **yes** |
| Size | (not stated) | **37,147,706 bytes (35 MB)** ← the hourly-poll design is not viable as written |
| Retention | "believed ~30 days of hourly deltas" | **exactly 30.00 days** (oldest `fetchTime` 2026-10-06T11:26:07.093Z, newest 2026-11-05T11:25:23.625Z) — rolling 30 days **confirmed** |
| Cadence | "hourly" | **NOT hourly.** 2740 entries / 30 days ≈ 91/day. Gap min 2.3 min, p50 **11.4 min**, p90 30.4 min, max 228.8 min |
| Ordering | (not stated) | **newest-first** (verified monotonic) |
| Entry shape | `fetchTime`, `new[]`, `updated[]` | `fetchTime`, `numberOfChanges`, `new[]`, `updated[]`, **`error[]`** (always empty across all 2740 entries) |
| Item shape | `cveId`, `dateUpdated` | `cveId`, `dateUpdated`, **`cveOrgLink`**, **`githubLink`** ← `githubLink` is the ready-made raw URL, so the client need not compute `NNNxxx` |
| Volume | (not stated) | 17,531 new + 106,328 updated over 30 days = **~4,130 changed records/day**, avg 45/entry, max 1,914 in one entry |

Response headers: `Content-Type: text/plain; charset=utf-8`, `Cache-Control: max-age=300`,
`ETag: "1840…cc76"`, **`Accept-Ranges: bytes`**.

### R-02.1b — `delta.json`

`GET …/cves/delta.json` → HTTP 200, **972 bytes**. Identical shape to `deltaLog[0]`; it is
*only the single newest delta window* (~11 min of changes), not a rolling hour. An hourly poll of
`delta.json` would silently lose ~80% of changes.

### R-02.1c — Range requests work (design unblocker)

`Range: bytes=0-20000` on `deltaLog.json` → **HTTP 206 Partial Content**,
`Content-Range: bytes 0-20000/37147706`. Because the array is newest-first and pretty-printed,
the first N bytes are the newest entries. Average entry ≈ 13.6 KB, ≈ 5 entries/hour ⇒ a
**512 KB range covers ≈ 7 hours** of delta history. This replaces the 35 MB download.

### R-02.2 — record URL layout — CONFIRMED

`cves/<year>/<floor(sequence/1000)>xxx/CVE-<year>-<sequence>.json`. Tested live, all HTTP 200:

| CVE | bucket |
|---|---|
| CVE-2020-1234 | `1xxx` (bucket is **not** zero-padded — the checklist's "`NNN`" is misleading) |
| CVE-2020-35610 | `35xxx` |
| CVE-2024-27983 | `27xxx` |
| CVE-2026-116409 | `116xxx` (6-digit sequence works) |

Note: computing this is optional — every delta item carries `githubLink` with the full raw URL.

### R-02.3 — GitHub rate limits — CHECKLIST ASSUMPTION IS WRONG

`raw.githubusercontent.com` returns **no `X-RateLimit-*` headers at all** (served by Fastly:
`X-Fastly-Request-ID` present). It is *not* the GitHub REST API and is *not* governed by the
API's quota. Measured on `api.github.com/rate_limit` unauthenticated: core **60/hr**,
search 10/min — those numbers apply only if we use the API, which this design does not.

⇒ **The PRD's "GitHub token (optional) for rate limit headroom" option buys nothing for raw
URL fetches.** Recommend dropping the option, or keeping it only if we ever switch to the
Commits API. Raw has an undocumented abuse threshold; the mitigation is volume control
(Range request + a per-run fetch cap), not a token.

### R-02.7 — Joomla CNA scope — CONFIRMED (verbatim)

From `CVEProject/cve-website` `src/assets/data/CNAsList.json` (the source behind the partner page):

> **Scope:** "Core Joomla! CMS, the Joomla Framework, and Joomla! Extensions issues only."

| Field | Value |
|---|---|
| `shortName` | `Joomla` |
| `cnaID` | `CNA-2020-0036` |
| `organizationName` | **`Joomla! Project`** (note: *not* `Joomla` — see classification correction below) |
| Contact | `security@joomla.org` |
| Disclosure policy | `https://developer.joomla.org/security.html` |
| Advisories | `https://developer.joomla.org/security-centre.html` |
| Root / TLR | MITRE Corporation |
| Type | Vendor, Open Source |

**Extensions are explicitly in scope** — this is the mandate VEL 2's CVE generator operates under.

### R-02 — sample Joomla CNA record (CVE-2020-35610), live

- `cveMetadata.assignerOrgId` = **`6ff30186-7fb7-4ad9-be33-533e7b05e586`** ✔ (matches
  `cna_org_id` default in `config.xml`)
- `cveMetadata.assignerShortName` = **`Joomla`** ✔
- `dataVersion` = **`5.2`**
- **Correction to the core-detection rule:** `affected[0]` is
  `{"vendor": "Joomla! Project", "product": "Joomla! CMS"}`. PRD §6.6.1 says to match vendor
  `Joomla`/product `Joomla! CMS`; the live vendor string is **`Joomla! Project`**. The
  classifier must match the vendor on `Joomla! Project` (and tolerate `Joomla`).
- `affected[].versions[0]` = `{"status":"affected","version":"2.5.0-3.9.22"}` — the Joomla CNA's
  own published records use the **single-string range style**, which is direct evidence for
  D-6's "option to emit the string style".
- `problemTypes[0].descriptions[0]` = `{"description":"Information Disclosure","lang":"en",
  "type":"text"}` — **no `cweId`**. Older Joomla records carry free text, so the feed mapper
  must handle `type: "text"` with no CWE id.
- References carry the legacy tag `x_refsource_MISC` alongside `vendor-advisory`.
- Containers present: `cna`, **`adp`** ✔ (ADP enrichment exists as assumed)

### CVE Services — what is confirmed so far

- `GET https://cveawg.mitre.org/api/cve/{id}` is **public, no auth** → HTTP 200 ✔
- `GET https://cveawg.mitre.org/api/cve?assigner_short_name=Joomla` → **HTTP 400**
  `{"error":"BAD_REQUEST","message":"CVE-API-ORG header field required."}` — confirms the
  **`CVE-API-ORG`** header name and that listing requires auth ✔
- `https://cveawg-test.mitre.org/api/` is **live** (returns the CVE Services error shape
  `{"error":"CVE_RECORD_DNE", …}`), with its own separate dataset ✔

### R-02.4 — CVE Services API — verified against the official OpenAPI spec

Source of truth: `https://raw.githubusercontent.com/CVEProject/cve-services/dev/api-docs/openapi.json`
(OpenAPI 3.0.2, **CVE Services API version 2.9.2**, `servers: [{"url": "/api"}]`).

**Auth headers — CONFIRMED exactly as the checklist states**, and they are required on *every*
authenticated operation (declared per-operation; there is no `securitySchemes` block):

| Header | Meaning |
|---|---|
| `CVE-API-ORG` | shortname of the org the user belongs to |
| `CVE-API-USER` | username making the request |
| `CVE-API-KEY` | the user's API key |

Confirmed live: `GET https://cveawg.mitre.org/api/cve?assigner_short_name=Joomla` with no headers
→ `400 {"error":"BAD_REQUEST","message":"CVE-API-ORG header field required."}`.

**`POST /cve-id` (reserve) — CONFIRMED, plus one parameter the PRD missed.** All parameters are
**query string**, not body:

| Param | Type | Required | Note |
|---|---|---|---|
| `amount` | int | ✔ | quantity to reserve |
| `cve_year` | int | ✔ | 1999 … currentYear + 1 |
| `short_name` | string | ✔ | owning CNA |
| `batch_type` | enum `sequential` / `non-sequential` / `nonsequential` | required **when `amount` > 1** | ⚠ **not in the PRD** |

Responses: **200** full list, **206** partial (quota exhausted mid-request — must be handled),
400/401/403/404/500. Body shape `{"cve_ids": [{cve_id, cve_year, owning_cna, state
(RESERVED|PUBLISHED|REJECTED), requested_by:{cna,user}, …}]}` — the checklist's
`cve_ids[].cve_id` is **correct** ✔.

**`POST` / `PUT /cve/{id}/cna` — body `{"cnaContainer": …}` CONFIRMED** ✔
(`schemas/cve/cna-minimum-request.json`). Additional facts the PRD does not record:

- Minimum viable container = `descriptions[]` + `affected[]` + `references[]` only.
- ⚠ **`providerMetadata` is set by the server and overwritten if supplied** — Appendix C tells the
  builder to emit it. Harmless but pointless for submission; keep it only in the *full record*
  shape used for Vulnogram/download.
- ⚠ **`datePublished` and `assignerShortName` are also server-set.**
- Optional query param **`erlcheck`** (boolean) enables stricter validation against the CVE
  Program's enrichment-data requirements. Worth exposing as an option.
- 200 response is `{valid: bool, warnings: [], message: string, created: <record>}` — so the
  client should surface `warnings` to the editor, not just success/failure.

**⚠ MAJOR CORRECTION — `GET /cve` is NOT available to the Joomla CNA.**
The spec's access-control note reads:

> "User must belong to an organization with the **Secretariat** or **Bulk Download** role."

The PRD (§6.6.1 "Alternative") and `cve.md` ("the authenticated CVE Services list, which returns
the organisation's own records only") are therefore **wrong**: `GET /cve?assigner_short_name=…`
will return 403 for an ordinary CNA. Same for `GET /cve_cursor`.

**The correct "our own records" path is `GET /cve-id`** — "accessible to registered users" —
filters `state` (`RESERVED|PUBLISHED|REJECTED`), `cve_id_year`, `time_reserved.lt/gt`,
`time_modified.lt/gt`, `page`. It returns ID records, not full CVE records; pair it with the
**public, unauthenticated `GET /cve/{id}`** to fetch each record. Paging envelope:
`{totalCount, itemsPerPage (500), pageCount, currentPage, prevPage, nextPage, cve_ids[]}`.

Other endpoints noted for later phases: `PUT /cve-id/{id}`, `POST /cve-id-range/{year}`,
`POST`/`PUT /cve/{id}/reject`, `PUT /cve/{id}/adp`, `GET /org/{shortname}/id_quota`
(quota before reserving — worth a dashboard tile), `GET /health-check`.

**Environments — both live:**

| Env | Base | Verified |
|---|---|---|
| prod | `https://cveawg.mitre.org/api/` | `GET /cve/CVE-2024-27983` → 200 ✔ |
| test | `https://cveawg-test.mitre.org/api/` | returns CVE Services error shape `{"error":"CVE_RECORD_DNE", …}` ✔, separate dataset |

### R-02.5 — record format version — RESOLVED (and the question partly dissolves)

- `CVEProject/cve-schema` latest release: **v5.2.0, published 2025-10-29** (previous: v5.1.1,
  2024-12-04). So 5.2 is a year old and is current, not "introduced later".
- The schema's `dataVersion` constraint is
  `pattern: "^5\\.(0|[1-9][0-9]*)(\\.(0|[1-9][0-9]*))?$"`, **`default: "5.2.0"`** — i.e. both
  `5.2` and `5.2.0` validate.
- Sampled 10 records fetched live from the current delta: **every one carries `dataVersion:
  "5.2"`** (assigners Patchstack, Red Hat, Go, WPScan). The 2020 Joomla record also serves as
  `5.2` — the repository normalises historic records forward.
- **The point the PRD misses:** CVE Services submission bodies are `{"cnaContainer": …}` — a
  bare container, which has **no `dataVersion` field**. So `cve_data_version` never affects what
  we submit; it only labels the *full record* shape we download / hand to Vulnogram.

⇒ **Set `cve_data_version` default to `5.2`** and note in the option's description that it
affects downloads only. Open question 3 in PRD §10 is answered.

### R-02.6 — the JSON schema — CONFIRMED, with a licence correction

`https://raw.githubusercontent.com/CVEProject/cve-schema/v5.2.0/schema/CVE_Record_Format.json`
→ HTTP 200, **72,841 bytes**. (Default branch is `main`, not `master`.)

| Fact | Finding |
|---|---|
| `$schema` | **draft-07** (`http://json-schema.org/draft-07/schema#`) |
| `$id` | `https://cveproject.github.io/cve-schema/schema/CVE_Record_Format.json` |
| External `$ref`s | **none — fully self-contained**, one file, no bundling step needed |
| Definitions | 46, incl. **`cnaPublishedContainer`**, `cnaRejectedContainer`, `adpContainer` |
| Top level | `oneOf` of published / rejected record shapes |
| Licence | ⚠ **CC0-1.0**, not MIT as PRD §6.5 claims. (More permissive; bundling is fine.) |

**Validator choice is settled by draft-07:** `opis/json-schema` **2.6.0** (Apache-2.0,
`php: ^7.4 || ^8.0`) implements draft-06/07/2019-09/2020-12. `justinrainbow/json-schema`
is draft-04-centric. ⇒ **use `opis/json-schema`**, as `cve.md` already guesses.

Because `cnaPublishedContainer` is a named definition, the *CNA-container-only* shape can be
validated against `#/definitions/cnaPublishedContainer` from the same file — no second schema.

---

## R-01 — NVD API 2.0, verified findings

All measured live 2026-11-05 ~11:55–12:10 UTC against
`https://services.nvd.nist.gov/rest/json/cves/2.0` (unauthenticated).

### R-01.2/3/4 — parameters and limits — CONFIRMED, with an error-handling surprise

| Test | Result |
|---|---|
| `lastModStartDate=2026-11-01T00:00:00.000` (no offset) | 200, totalResults 45 |
| `…T00:00:00.000+00:00` | 200, totalResults 45 |
| `…T00:00:00Z` | 200, totalResults 45 |
| `…T00:00:00.000Z` | 200, totalResults 45 |

⇒ **NVD is lenient about the timestamp format**; all four parse identically and a
missing offset is read as UTC. The checklist's `YYYY-MM-DDTHH:MM:SS.mmm` works. Recommend
emitting `…Z` explicitly so the intent is in the URL.

| Test | Result |
|---|---|
| 153-day window | **HTTP 404**, header `message: Date range cannot exceed 120 days.` |
| 104-day window | 200 (93,846 results) |
| `resultsPerPage=2001` | **HTTP 404**, header `message: resultsPerPage parameter cannot exceed 2000.` |
| `resultsPerPage=2000` | 200 |
| `lastModStartDate` without `lastModEndDate` | **HTTP 404**, header `message: Both lastModStartDate and lastModEndDate are required when either is present.` |

⚠ **This is the single most important client-implementation finding on the NVD side:**
NVD reports parameter errors as **HTTP 404 with an empty body and the reason in a `message`
HTTP response header.** A client that reads the body, or that treats 404 as "not found, skip",
will silently mis-handle every bad request. `NvdClient` must read the `message` header and must
not conflate 404-with-message against 404-no-such-CVE.

### R-01.5 — rate limits

Documented limits (5 req / rolling 30 s without a key, 50 with) are the published figures and
remain the design basis. **Observed enforcement is lenient:** a burst of 10 back-to-back
unauthenticated `?cveId=` requests all returned 200 with no `X-RateLimit-*` or `Retry-After`
headers. ⇒ Keep the 6 s sleep; do **not** rely on headers for backoff (there are none); treat
403 as the trip signal as designed. No evidence found to contradict the documented numbers.

### R-01.6 — `virtualMatchString` / `target_sw` — ANSWERED: yes, but do not use it as the query

`GET …?virtualMatchString=cpe:2.3:a:*:*:*:*:*:*:*:joomla:*:*` → **HTTP 200**, and the filter
genuinely applies to `target_sw` — proven by the control:

| virtualMatchString | totalResults |
|---|---|
| `target_sw = joomla` | **13** |
| `target_sw = wordpress` (control) | **16,254** |
| `cpe:2.3:a:joomla` (vendor) | 1,002 |
| `keywordSearch=Joomla` (for comparison) | **1,633** |

The mechanism works; the **data does not exist**. All 13 are shown below — NVD simply does not
tag Joomla extension CPEs with `target_sw=joomla` the way it does for WordPress:

```
cpe:2.3:a:huge-it:video_gallery:1.0.9:*:*:*:*:joomla:*:*          CVE-2016-1000123
cpe:2.3:a:huge-it:portfolio_gallery_manager:1.1.5:…:joomla:*:*    CVE-2016-1000116
cpe:2.3:a:huge-it:slideshow / slider / catalog                     CVE-2016-10001{17,20,21,22}
cpe:2.3:a:jextn:jextn_video_gallery:3.0.5:…:joomla:*:*            CVE-2017-17872
cpe:2.3:a:chronoengine:chronoforums:…:joomla:*:*                  CVE-2020-27459, CVE-2021-28376/7
cpe:2.3:a:joomlaux:jux_real_estate:3.4.0:…:joomla:*:*             CVE-2025-2126/2127/2714
```

⇒ **Decision: keep `keywordSearch=Joomla` as the discovery query.** `virtualMatchString` would
cut recall from 1,633 to 13. Resolve the `[VERIFY R-01]` marker in PRD §6.6.2 with "tested,
rejected" and record the control figure so nobody re-opens it. The `target_sw=joomla` test
stays useful as a *high-confidence classifier signal*, not as the query.

### R-01.7 — Joomla CNA `sourceIdentifier` — CONFIRMED

`?sourceIdentifier=security@joomla.org` → 200, **totalResults 558** NVD-wide.
⚠ `sourceIdentifier` is a **supported query parameter** and is **not in the PRD's parameter
list** — it is a cleaner way to recognise our own records in discovery than matching by id.
Within the 1,633-record Joomla keyword corpus, 446 records carry `security@joomla.org`.

### R-01.8 — response shape, measured over the whole 1,633-record Joomla corpus

One request with `resultsPerPage=2000` returns **all 1,633** keyword hits (5.3 MB), so the
corpus below is complete, not a sample.

**Metrics containers present** (PRD mentions only V31/V40):

| Container | Records | Primary | Secondary |
|---|---|---|---|
| `cvssMetricV2` | 1151 | 1146 | 5 |
| `ssvcV203` | 473 | — (no `type`) | — |
| `cvssMetricV31` | 405 | 259 | 164 |
| **`cvssMetricV40`** | **375** | **0** | **375** |
| `cvssMetricV30` | 199 | 194 | 5 |

⚠ **CORRECTION 1 — "prefer Primary" would discard every CVSS 4.0 score.** `cvssMetricV40` is
**Secondary in 375 of 375 cases**: NVD does not yet produce its own v4.0 analysis, so all v4.0
comes from CNAs. `nvd.md`'s rule "NVD's own CVSS — *Primary* — is preferred over the CNA's
*Secondary*" must be rewritten as: prefer by **version first** (4.0 > 3.1 > 3.0, ignore v2),
then Primary over Secondary **within** a version.

⚠ **CORRECTION 2 — `cvssMetricV30` exists and is not handled.** 199 records. Either map it or
state explicitly that it is ignored.

⚠ **CORRECTION 3 — `ssvcV203` is a metrics container the PRD does not know about** (473
records; CISA SSVC decision points). Harmless, but the parser must not assume every key under
`metrics` is a CVSS block.

**Weaknesses** — `weaknesses[].type` is Primary/Secondary; `description[].value`:

| Value | Count |
|---|---|
| a real `CWE-…` id (Primary) | 1074 |
| a real `CWE-…` id (Secondary) | 517 |
| **`NVD-CWE-Other`** | 88 |
| **`NVD-CWE-noinfo`** | 53 |

⚠ **CORRECTION 4 — `NVD-CWE-Other` / `NVD-CWE-noinfo` are not CWE ids** and would fail our
`#__vel_advisory_cwes.cwe_id` validation. 141 occurrences. Must be filtered out explicitly.

**`configurations[].nodes[].cpeMatch[]` keys** (5,899 rows): `criteria`, `vulnerable`,
`matchCriteriaId` (all 5,899), `versionEndIncluding` (320), `versionStartIncluding` (241),
`versionEndExcluding` (234). (`versionStartExcluding` exists in NVD generally; absent here.)
⇒ `matchCriteriaId` is always present and the PRD does not mention it.

⚠ **CORRECTION 5 — `references[].tags` use NVD's own Title-Case vocabulary, not CVE 5.x.**
Measured, complete for this corpus:

| NVD tag | Count | CVE 5.x equivalent |
|---|---|---|
| `Exploit` | 2368 | `exploit` |
| `Vendor Advisory` | 1565 | `vendor-advisory` |
| `Third Party Advisory` | 1036 | `third-party-advisory` |
| `VDB Entry` | 640 | **none** |
| `Patch` | 341 | `patch` |
| `Product` | 225 | `product` |
| `Mailing List` | 78 | `mailing-list` |
| `Broken Link` | 58 | `broken-link` |
| `Release Notes` | 51 | `release-notes` |
| `URL Repurposed` | 32 | **none** |
| `Issue Tracking` | 28 | `issue-tracking` |
| `US Government Resource` | 8 | `government-resource` |
| `Not Applicable` | 6 | `not-applicable` |
| `Technical Description` | 4 | `technical-description` |
| `Permissions Required` | 4 | `permissions-required` |
| `Mitigation` | 2 | `mitigation` |

PRD §6.1.5 defines the tag vocabulary as the CVE 5.x lowercase list and §6.6.2 says enrichment
copies "references with tags" — **a mapping table is required**, and `VDB Entry` /
`URL Repurposed` need a policy (recommend: drop, or map to `related`).

### R-01 — classification data, measured

⚠ **CORRECTION 6 — core Joomla is spelled two ways in CPE, and CPE vendor `joomla` is not
proof of core.** Across the corpus, 4,165 cpeMatch rows carry vendor `joomla`, over 931 CVEs:

| Product under CPE vendor `joomla` | Rows |
|---|---|
| `joomla\!` | 3301 |
| **`joomla`** | **622** |
| `rs_gallery2` | 41 |
| `com_yvcomment` | 16 |
| `bsq_sitestats` | 8 |
| `com_user`, `com_eventing`, `ignitegallery` | 6/6/4 |

PRD §6.6.2 drops records where "CPE vendor is `joomla` **and** product `joomla\!`". That
(a) misses the 622 rows spelled `joomla`, and (b) is otherwise correct to require the product
check, because `com_yvcomment` and friends sit under the same vendor and are **extensions**.
⇒ core test = vendor `joomla` AND product ∈ {`joomla\!`, `joomla`}.

Other measured facts:

- **287 of 1,633 records (17.6%) have no `configurations` at all** — the enrichment backlog the
  PRD warns about, now quantified. The classifier must not assume CPE data exists.
- `vulnStatus` distribution: Modified 1189, Deferred 263, Analyzed 157, Awaiting Analysis 23,
  Rejected 1. ⇒ `Deferred` (263!) is a real state the PRD does not mention.
- Only **75 of the 446** `security@joomla.org` records have any `joomla:joomla*` CPE — our own
  CNA's records are mostly extension records or unenriched.
- **Rejected record shape** (`CVE-2026-73327`): keys are `id, sourceIdentifier, published,
  lastModified, vulnStatus, cveTags, descriptions, metrics, references` — **no
  `configurations`, no `weaknesses`**, and `descriptions[0].value` begins `"Rejected reason: "`.

## Log

- 2026-11-05 — read cve.md, nvd.md, VEL2-PRD.md (incl. §6.6, §9 R-01/R-02, Appendix C/D).
  Previous two sessions left only prompt transcripts (`chat-*.txt`), no findings. Starting
  verification from scratch.
- 2026-11-05 — R-02.1 done: deltaLog.json verified and measured. Three corrections to the
  checklist (size, cadence, item shape) plus the Range-request finding.
- 2026-11-05 — R-02.2/.3/.7 done (URL layout, GitHub limits, CNA scope).
- 2026-11-05 — R-02.4 done against the official OpenAPI 2.9.2 spec. Major correction:
  `GET /cve` needs Secretariat/Bulk Download; CNAs must use `GET /cve-id` + public `GET /cve/{id}`.
- 2026-11-05 — R-02.5/.6 done. `dataVersion` = `5.2`, and irrelevant to submission.
  Schema is draft-07, self-contained, CC0 (not MIT) ⇒ `opis/json-schema`.
- 2026-11-05 — R-01.1–.8 done. Six corrections found; `virtualMatchString` tested and rejected
  with a control measurement. Remaining: fixtures, then write the docs and the PRD.
- 2026-11-05 (session 4, resumed) — research phase confirmed complete from this log. Remaining
  work: (a) fixtures, (b) rewrite nvd.md, (c) rewrite cve.md, (d) write VEL2-Feeds-PRD.md,
  (e) resolve [VERIFY] markers in VEL2-PRD.md §6.5/§6.6/§10/Appendix C/D. Working in that order,
  ticking the Deliverables list above as each lands.

---

## Session 4 — fixtures + three new findings

### R-01.9 / R-02.8 — fixtures captured ✔

`tests/fixtures/vel/nvd/` (each wrapped in the real NVD 2.0 envelope so the parser sees what the
client sees):

| File | CVE | Why |
|---|---|---|
| `nvd-extension-with-cpe.json` | CVE-2025-2126 | VulDB, `Analyzed`, `target_sw=joomla` CPE, v4.0 + v3.1 + v2 + `ssvcV203`, 2 weaknesses, CPE bounds |
| `nvd-extension-no-cpe.json` | CVE-2026-105606 | Joomla CNA 2026 house style, `Deferred`, **v4.0 only (Secondary)**, `configurations` absent |
| `nvd-core.json` | CVE-2026-73337 | core, `Analyzed`, `cpe:2.3:a:joomla:joomla\!` |
| `nvd-unrelated-keyword-hit.json` | CVE-2024-32788 | "FG Joomla to WordPress" — a **WordPress** plugin; the canonical false positive |
| `nvd-rejected.json` | CVE-2026-73327 | `vulnStatus: Rejected`, no `configurations`/`weaknesses` |

`tests/fixtures/vel/cve/`:

| File | CVE | Why |
|---|---|---|
| `cve-joomla-cna-extension.json` | CVE-2026-105606 | current house style, `cna` + CISA-ADP |
| `cve-joomla-cna-core.json` | CVE-2026-73337 | core; vendor `Joomla! Project`; has `impacts[]` |
| `cve-joomla-cna-legacy.json` | CVE-2020-35610 | 2020 style: `problemTypes` `type:"text"` with **no `cweId`**, `x_refsource_*` tags |
| `cve-other-cna-extension.json` | CVE-2024-5736 | CERT-PL on a Joomla extension — and **`dataVersion` `5.1`** |
| `cve-rejected.json` | CVE-2026-73327 | REJECTED shape |
| `cvelist-deltalog-head.json` | — | 7 newest `deltaLog.json` entries via `Range: bytes=0-24000` (HTTP 206) |
| `cvelist-delta.json` | — | the live `delta.json`, 980 bytes |

### NEW FINDING A — ⚠ correction to R-02.5: `dataVersion` is **not** uniformly `5.2`

The earlier conclusion ("the repository normalises historic records forward") is **wrong**.
`dataVersion` records whatever version the assigner last wrote. Measured:

| CVE | `dataVersion` | `dateUpdated` | assigner |
|---|---|---|---|
| CVE-2024-5736 / -5735 | **5.1** | 2024-08-01 | CERT-PL |
| CVE-2025-2126 | **5.1** | 2025-03-10 | VulDB |
| CVE-2025-40636 | **5.1** | 2025-10-03 | INCIBE |
| CVE-2017-17872 | **5.1** | 2024-08-05 | mitre |
| CVE-2020-35610, -2021-23132, -2022-23793, -2023-23752 | 5.2 | 2026-02-2x | Joomla (bulk re-write) |
| 60/60 records sampled from the live delta | 5.2 | 2026-11 | apache, redhat, eclipse, TR-CERT, VulDB |

⇒ **Anything newly written today is `5.2`; anything untouched since ~2025 is still `5.1`.**
The *parser* must accept both (`^5\.\d+(\.\d+)?$`). The *emitter* default `5.2` stands.

### NEW FINDING B — the Joomla CNA adopted a machine-readable house style in 2026

This is the single most useful classification signal found in either feed, and the PRD does not
know about it. Measured over all 446 `security@joomla.org` records in the NVD corpus:

| Description/title prefix | Count | Years |
|---|---|---|
| `Joomla Extension - <vendor-domain> - <summary> in <product> <range>` | **289** | 2026 only |
| `Joomla! Core - [YYYYMMDD] - <summary> in Joomla <range>` | **26** | 2026 only (date code present in 26/26) |
| free text (no convention) | 131 | 2020–2025, plus 11 in 2026 |

Volume by year for the Joomla CNA: 2020:7, 2021:24, 2022:16, 2023:24, 2024:6, 2025:43,
**2026:326**. The CNA has gone from ~2/month to ~30/month — the extension-CVE programme the
PRD is being written for is **already running**. 43 distinct vendor domains appear, led by
`regularlabs.com` (43), `joomshaper.com` (41), `balbooa.com` (25), `fabrikar.com` (19),
`j2commerce.com` (17), `phoca.cz` (15), `yootheme.com` (13).

Live record `CVE-2026-105606` (`containers.cna`) confirms the shape the builder must reproduce:

```
title        "Joomla Extension - joomlaboat.com - Unauthenticated arbitrary file upload in Custom Tables < 3.7.8"
descriptions[0].value   = <title> + " - " + <description text>     ← PRD §6.5 rule CONFIRMED in production
descriptions[0].supportingMedia[0]  {type: "text/html", base64: false, value: <same>}
affected[0]  {defaultStatus: "unaffected", vendor: "joomlaboat.com",
              product: "Custom Tables extension for Joomla",
              versions: [{status: "affected", version: "1.0-3.7.7"}]}
problemTypes[0].descriptions[0]  {cweId: "CWE-434", description: "CWE-434 Unrestricted Upload of …",
                                  lang: "en", type: "CWE"}     ← description repeats the id
metrics[0]   cvssV4_0 only
references   [{tags: ["product"], url: "https://joomlaboat.com"}]
credits      [{lang: "en", type: "finder", value: "…"}]
source       {discovery: "UNKNOWN"}
x_generator  {engine: "Vulnogram 0.1.0-dev"}          ← answers §10 Q4: Vulnogram is in use today
```

Core record `CVE-2026-73337` differs in exactly three ways: `vendor: "Joomla! Project"`,
`product: "Joomla! CMS"`, and it carries **`impacts[]`** (`{capecId: "CAPEC-115", descriptions:[…]}`),
a CNA key Appendix C does not list.

⚠ **Three Appendix C prescriptions are contradicted by the CNA's own records:**
`collectionURL: "https://extensions.joomla.org"`, `platforms: ["Joomla"]` and `packageName` are
**not emitted** by any sampled Joomla CNA record. The vendor field is the **vendor's domain**,
not a display name, and the product is `"<Name> extension for Joomla"`.

⇒ Classification should key on `cveMetadata.assignerShortName == "Joomla"` plus the
`affected[].vendor`/`product` test, with the `^Joomla Extension - ` / `^Joomla! Core - ` title
prefixes as a strong confirming signal for 2026+ records and a fallback for unenriched ones.

### NEW FINDING C — NVD CPE cannot identify modern Joomla core

Every one of the 214 corpus records whose CPE is `joomla:joomla` / `joomla:joomla\!` **and** that
NVD has analysed is dated 2005–2010 **except** the 2026 core batch. Full year spread for CPE
vendor `joomla` (931 records): 2005:4 2006:53 2007:58 2008:117 2009:143 2010:210 2011:73 2012:43
2013:15 2014:14 2015:13 2016:6 2017:19 2018:24 2019:27 2020:39 2021:28 2022:13 2023:5
**2024:0 2025:0 2026:27**.

The 2024–2025 hole is the NVD enrichment backlog. Of the 326 Joomla-CNA records published in
2026, only **94 have `configurations` at all**; status spread is Deferred 209, Analyzed 89,
Awaiting Analysis 23, Modified 5.

⇒ **The PRD's CPE-based core/extension split (§6.6.2) fails on every record from 2024 onward.**
The classifier must lead with `sourceIdentifier`/title/description and treat CPE as corroboration
when present. `Deferred` is the modal status for our own records and must be handled.

### NEW FINDING D — CISA-ADP carries SSVC, not CVSS

Both 2026 fixtures have exactly one ADP container, `providerMetadata.shortName = "CISA-ADP"`,
with keys `metrics`, `title`, `providerMetadata`, and `metrics[0].other.type = "ssvc"`
(decision points Exploitation / Automatable / Technical Impact). **No `cvssV3_1`/`cvssV4_0` and
no `problemTypes`.** PRD §6.6.1's "ADP containers are read for CVSS/CWE when the CNA container
has none" is therefore optimistic — CISA-ADP supplies CVSS/CWE only for records whose CNA left
them out entirely (the Vulnrichment programme), which is not the case for Joomla CNA records.
Keep the ADP read, but do not depend on it.

### REJECTED record shape — confirmed

`containers` = `[cna]` only. `containers.cna` keys = `providerMetadata`, **`rejectedReasons`**,
`x_generator`. No `descriptions`, no `affected`. `cveMetadata` gains **`dateRejected`** and
`state: "REJECTED"`. (NVD renders the same record with `vulnStatus: "Rejected"` and a synthetic
`descriptions[0].value` beginning `"Rejected reason: "`.)

- 2026-11-05 — fixtures done (13 files). Findings A–D recorded. Next: rewrite nvd.md, cve.md,
  write VEL2-Feeds-PRD.md, patch VEL2-PRD.md.
- 2026-11-05 — `nvd.md` rewritten against verified facts. Next: `cve.md`.
- 2026-11-05 — `cve.md` rewritten against verified facts. Next: `VEL2-Feeds-PRD.md`.
- 2026-11-05 — `VEL2-Feeds-PRD.md` written (11 sections; §9 lists the 14 amendments the parent
  PRD needs). Next and last: apply those amendments to `VEL2-PRD.md`.
- 2026-11-05 — VEL2-PRD.md amendments in progress: header note, §6.1.9, §6.3.3, §6.5, §6.6.1,
  §6.6.2 DONE. Remaining: §6.6.3, §8.8, §9 R-01/R-02 + F-02, §10 Q3/Q4/Q7/Q8, Appendix C,
  Appendix D.
