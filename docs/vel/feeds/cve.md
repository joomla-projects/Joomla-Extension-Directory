# The CVE Program feed (R-02, F-02)

**Status: R-02 complete.** Every statement below was measured against the live services on
2026-11-05, or read from the official OpenAPI specification. Where a measurement contradicts the
PRD, the contradiction is called out. Raw measurements:
[`RESEARCH-PROGRESS.md`](RESEARCH-PROGRESS.md). Design consequences:
[`VEL2-Feeds-PRD.md`](VEL2-Feeds-PRD.md).

## What it does

`vel.feed_cna` reads the public `CVEProject/cvelistV5` mirror's delta log, fetches each record
changed since the last run, maps it, classifies it and stores it in *Feed Items*:

| Classification | Rule | What happens |
|---|---|---|
| `joomla_cna` | `assignerShortName` is `Joomla` (or `assignerOrgId` is ours) and the affected products are not Joomla core | a draft advisory is created automatically (option `feed_auto_create`), or offered for review if one with that CVE id exists |
| `joomla_core` | `affected[].vendor` is `Joomla! Project` and product `Joomla! CMS` | listed for the record; never an advisory |
| `joomla_extension_other_cna` | another CNA's record whose products, platforms, collection URL, title or description say "Joomla" | offered for review (option `feed_import_other_cnas`) |
| `unrelated` | none of the above | not stored |

A record is keyed by `(source, cve_id)` and hashed on its content minus `dateUpdated`: unchanged
records are no-ops; changed ones return to *pending* with the advisory still linked.

## The Joomla CNA

Confirmed from `CVEProject/cve-website` `src/assets/data/CNAsList.json`, the data behind the
partner page:

| Field | Value |
|---|---|
| `shortName` | `Joomla` |
| `cnaID` | `CNA-2020-0036` |
| `organizationName` | **`Joomla! Project`** |
| `orgId` | `6ff30186-7fb7-4ad9-be33-533e7b05e586` |
| Contact | `security@joomla.org` |
| Disclosure policy | `https://developer.joomla.org/security.html` |
| Advisories | `https://developer.joomla.org/security-centre.html` |
| Root / TLR | MITRE Corporation |
| Type | Vendor, Open Source |

> **Scope:** "Core Joomla! CMS, the Joomla Framework, and Joomla! Extensions issues only."

Extensions are explicitly in scope. That sentence is the mandate VEL 2's CVE generator operates
under, and it is why this component exists.

## The CNA's house style — observed, and what the builder must reproduce

⚠ **This section is new information the PRD was written without.** In 2026 the Joomla CNA
adopted a strict, machine-readable convention, and it is already running at scale.

Records published by `security@joomla.org`, by year:

```
2020:7  2021:24  2022:16  2023:24  2024:6  2025:43  2026:326
```

Of the 326 published in 2026, **315 follow one of two title templates**:

| Template | Count |
|---|---|
| `Joomla Extension - <vendor-domain> - <summary> in <product> <version range>` | 289 |
| `Joomla! Core - [YYYYMMDD] - <summary> in Joomla <version range>` | 26 (date code in 26/26) |

43 distinct vendor domains appear, led by `regularlabs.com` (43), `joomshaper.com` (41),
`balbooa.com` (25), `fabrikar.com` (19), `j2commerce.com` (17), `phoca.cz` (15),
`yootheme.com` (13), `themexpert.com` (10), `ordasoft.com` (10).

The live extension record `CVE-2026-105606` (`containers.cna`, verbatim shape):

```
title        "Joomla Extension - joomlaboat.com - Unauthenticated arbitrary file upload in Custom Tables < 3.7.8"
descriptions[0].value            <title> + " - " + <description text>
descriptions[0].supportingMedia  [{type: "text/html", base64: false, value: <the same string>}]
affected[0]  {defaultStatus: "unaffected",
              vendor:  "joomlaboat.com",                        ← the vendor's DOMAIN
              product: "Custom Tables extension for Joomla",    ← "<Name> extension for Joomla"
              versions: [{status: "affected", version: "1.0-3.7.7"}]}
problemTypes[0].descriptions[0]  {cweId: "CWE-434", type: "CWE", lang: "en",
                                  description: "CWE-434 Unrestricted Upload of File with Dangerous Type"}
metrics[0]   cvssV4_0 only
references   [{tags: ["product"], url: "https://joomlaboat.com"}]
credits      [{lang: "en", type: "finder", value: "…"}]
source       {discovery: "UNKNOWN"}
x_generator  {engine: "Vulnogram 0.1.0-dev"}
```

Three things follow directly:

1. **PRD §6.5's description rule is confirmed in production.** `descriptions[0].value` really is
   *title* + `" - "` + *description text*, with the HTML in `supportingMedia`. Keep it.
2. ⚠ **Three Appendix C prescriptions are not what the CNA actually emits.** No sampled Joomla
   CNA record carries `collectionURL: "https://extensions.joomla.org"`, `platforms: ["Joomla"]`
   or `packageName`. The `vendor` is a **domain**, not a display name. Appendix C must be
   corrected or the option made explicit — see `VEL2-Feeds-PRD.md` §4.
3. **`x_generator.engine` is `Vulnogram 0.1.0-dev`.** The team works in Vulnogram today, which
   bears on PRD §10 Q4 (full-record vs CNA-container toolbar default).

The core record `CVE-2026-73337` differs in exactly three ways: `vendor: "Joomla! Project"`,
`product: "Joomla! CMS"`, and it carries **`impacts[]`**
(`{capecId: "CAPEC-115", descriptions: […]}`) — a CNA key Appendix C does not list.

⚠ **The core-detection rule in PRD §6.6.1 is wrong on the vendor string.** It says vendor
`Joomla`; the live value is **`Joomla! Project`**. Match on `Joomla! Project`, tolerate `Joomla`.

Older records are free-form (131 of the 446 have no title convention) and differ structurally —
see the `cve-joomla-cna-legacy.json` fixture: `problemTypes[0].descriptions[0]` is
`{"description": "Information Disclosure", "lang": "en", "type": "text"}` with **no `cweId`**,
and references carry the legacy `x_refsource_MISC` tag alongside `vendor-advisory`. The mapper
must handle `type: "text"` without a CWE id.

## Source: `CVEProject/cvelistV5` — measured

### `deltaLog.json`

`GET https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/deltaLog.json` → HTTP 200.

| Fact | Assumption | Measured |
|---|---|---|
| Size | (not stated) | **37 MB** — the naive "poll it hourly" design is not viable |
| Retention | "~30 days" | **exactly 30.00 days** ✔ (rolling) |
| Cadence | "hourly" | ⚠ **not hourly** — 2,740 entries / 30 days ≈ 91/day; gaps p50 **11.4 min**, p90 30.4 min, max 228.8 min |
| Ordering | (not stated) | **newest first** |
| Entry keys | `fetchTime`, `new[]`, `updated[]` | plus `numberOfChanges` and **`error[]`** (empty in all 2,740) |
| Item keys | `cveId`, `dateUpdated` | plus `cveOrgLink` and **`githubLink`** |
| Volume | (not stated) | 17,531 new + 106,328 updated / 30 days ≈ **4,130 records/day**; avg 45/entry, max 1,914 |

Headers: `Content-Type: text/plain; charset=utf-8`, `Cache-Control: max-age=300`, `ETag`,
**`Accept-Ranges: bytes`**.

**`githubLink` is the ready-made raw URL** — the client does not need to compute the bucket.

### Range requests make the design work

`Range: bytes=0-24000` → **HTTP 206 Partial Content**, `Content-Range: bytes 0-24000/37164365`.
Because the array is newest-first and pretty-printed, the first N bytes are the newest entries.
An entry averages ~13.6 KB and ~5 arrive per hour, so **a 512 KB range covers roughly 7 hours**
of history. `CvelistClient` requests a range sized from the gap since the last successful run,
parses up to the last complete entry, and falls back to a full fetch only if the server ignores
`Range` or the window needs more than the cap.

### ⚠ `delta.json` is not an hourly delta

972–980 bytes, same shape as `deltaLog[0]` — it is **only the single newest delta window**
(~11 minutes of changes). Polling it hourly would silently lose ~80% of changes. Do not use it
as the feed source; it is kept as a cheap liveness probe only.

### Record URL layout — confirmed

`cves/<year>/<floor(sequence/1000)>xxx/CVE-<year>-<sequence>.json`. The bucket is **not**
zero-padded, so the checklist's "`NNN`" was misleading. Verified live, all HTTP 200:

| CVE | Bucket |
|---|---|
| CVE-2020-1234 | `1xxx` |
| CVE-2020-35610 | `35xxx` |
| CVE-2024-27983 | `27xxx` |
| CVE-2026-116409 | `116xxx` (6-digit sequences work) |

### ⚠ GitHub rate limits — the checklist assumption was wrong

`raw.githubusercontent.com` returns **no `X-RateLimit-*` headers at all**. It is served by Fastly
(`X-Fastly-Request-ID`), is not the GitHub REST API, and is not governed by the API's quota. The
60/hr unauthenticated figure measured on `api.github.com/rate_limit` applies only to the API,
which this design does not use.

⇒ **A GitHub token buys nothing for raw URL fetches.** The PRD's "GitHub token (optional) for
rate limit headroom" option should be dropped. Raw has an undocumented abuse threshold; the
mitigation is volume control — the Range request plus a per-run fetch cap — not a token.

### `dataVersion` — accept both 5.1 and 5.2

`dataVersion` records whatever the assigner last wrote; the repository does **not** normalise
historic records forward.

| Record | `dataVersion` | `dateUpdated` |
|---|---|---|
| CVE-2024-5736 (CERT-PL) | **5.1** | 2024-08-01 |
| CVE-2025-2126 (VulDB) | **5.1** | 2025-03-10 |
| CVE-2025-40636 (INCIBE) | **5.1** | 2025-10-03 |
| CVE-2020-35610, CVE-2023-23752 (Joomla) | 5.2 | 2026-02 (bulk re-write) |
| 60 of 60 sampled from the live delta | 5.2 | 2026-11 |

Anything written today is `5.2`; anything untouched since ~2025 is still `5.1`. The **parser**
accepts `^5\.\d+(\.\d+)?$`; the **emitter** defaults to `5.2`.

### REJECTED record shape

`containers` is `[cna]` only. `containers.cna` has exactly `providerMetadata`,
**`rejectedReasons`**, `x_generator` — no `descriptions`, no `affected`. `cveMetadata` gains
**`dateRejected`** and `state: "REJECTED"`.

### ⚠ ADP containers carry SSVC, not CVSS

Both 2026 fixtures have one ADP container, `providerMetadata.shortName = "CISA-ADP"`, with keys
`metrics`, `title`, `providerMetadata` and `metrics[0].other.type = "ssvc"` (Exploitation /
Automatable / Technical Impact). **No `cvssV3_1`/`cvssV4_0`, no `problemTypes`.** PRD §6.6.1's
"ADP containers are read for CVSS/CWE when the CNA container has none" is optimistic: CISA
Vulnrichment supplies CVSS/CWE only where the CNA supplied none at all, which is not the case
for Joomla CNA records. Keep the ADP read as a fallback; do not depend on it.

## CVE Services API — verified against the official specification

Source of truth: `https://raw.githubusercontent.com/CVEProject/cve-services/dev/api-docs/openapi.json`
— OpenAPI 3.0.2, **CVE Services API 2.9.2**, `servers: [{"url": "/api"}]`.

| Environment | Base | Verified |
|---|---|---|
| prod | `https://cveawg.mitre.org/api/` | `GET /cve/CVE-2024-27983` → 200 ✔ |
| test | `https://cveawg-test.mitre.org/api/` | live, separate dataset ✔ |

### Auth — confirmed exactly as assumed

| Header | Meaning |
|---|---|
| `CVE-API-ORG` | shortname of the org the user belongs to |
| `CVE-API-USER` | username making the request |
| `CVE-API-KEY` | the user's API key |

Required on *every* authenticated operation; declared per-operation (there is no
`securitySchemes` block). Confirmed live: `GET /api/cve?assigner_short_name=Joomla` with no
headers → `400 {"error":"BAD_REQUEST","message":"CVE-API-ORG header field required."}`.

### ⚠ MAJOR CORRECTION — `GET /cve` is not available to the Joomla CNA

The specification's access-control note on `GET /cve` and `GET /cve_cursor` reads:

> "User must belong to an organization with the **Secretariat** or **Bulk Download** role."

The PRD's §6.6.1 "Alternative" source — `GET /api/cve?assigner_short_name=Joomla&time_modified.gt=…`
— will therefore return **403** for an ordinary CNA. **That alternative does not exist and must
be removed from the design.**

**The correct "our own records" path is `GET /cve-id`**, which the spec marks "accessible to
registered users". It filters on `state` (`RESERVED|PUBLISHED|REJECTED`), `cve_id_year`,
`time_reserved.lt/gt`, `time_modified.lt/gt`, `page`, and returns *ID* records, not full CVE
records. Paging envelope: `{totalCount, itemsPerPage (500), pageCount, currentPage, prevPage,
nextPage, cve_ids[]}`. Pair it with the **public, unauthenticated `GET /cve/{id}`** to fetch each
record.

### `POST /cve-id` (reserve) — confirmed, plus one parameter the PRD missed

All parameters are **query string**, not body:

| Param | Type | Required | Note |
|---|---|---|---|
| `amount` | int | ✔ | quantity to reserve |
| `cve_year` | int | ✔ | 1999 … currentYear + 1 |
| `short_name` | string | ✔ | owning CNA |
| `batch_type` | enum `sequential` / `non-sequential` / `nonsequential` | required **when `amount` > 1** | ⚠ **not in the PRD** |

Responses: **200** full list, **206 partial** (quota exhausted mid-request — must be handled),
400/401/403/404/500. Body `{"cve_ids": [{cve_id, cve_year, owning_cna, state, requested_by:
{cna, user}, …}]}` — the assumed `cve_ids[].cve_id` path is correct ✔.

### `POST` / `PUT /cve/{id}/cna` — confirmed, with four refinements

Body `{"cnaContainer": …}` ✔ (`schemas/cve/cna-minimum-request.json`).

- Minimum viable container is `descriptions[]` + `affected[]` + `references[]`.
- ⚠ **`providerMetadata` is set by the server and overwritten if supplied.** Appendix C tells the
  builder to emit it — harmless but pointless for submission. Keep it only in the *full record*
  shape used for download and Vulnogram.
- ⚠ **`datePublished` and `assignerShortName` are also server-set.**
- Optional query param **`erlcheck`** (boolean) enables stricter validation against the CVE
  Program's enrichment-data requirements. Worth exposing as an option.
- The 200 response is `{valid, warnings: [], message, created: <record>}` — surface `warnings`
  to the editor, not just success/failure.

Since a submission body is a bare container, it has **no `dataVersion` field at all**. The
`cve_data_version` option therefore never affects what we submit; it labels only the full record
we download or hand to Vulnogram.

### Other endpoints for later phases

`PUT /cve-id/{id}`, `POST /cve-id-range/{year}`, `POST`/`PUT /cve/{id}/reject`,
`PUT /cve/{id}/adp`, **`GET /org/{shortname}/id_quota`** (worth a dashboard tile — check quota
before reserving), `GET /health-check`.

## The JSON schema

`https://raw.githubusercontent.com/CVEProject/cve-schema/v5.2.0/schema/CVE_Record_Format.json`
→ HTTP 200, 72,841 bytes. Latest release **v5.2.0, 2025-10-29** (previous v5.1.1, 2024-12-04).
The default branch is `main`, not `master`.

| Fact | Finding |
|---|---|
| `$schema` | **draft-07** |
| `$id` | `https://cveproject.github.io/cve-schema/schema/CVE_Record_Format.json` |
| External `$ref`s | **none — fully self-contained**, one file, no bundling step |
| Definitions | 46, including `cnaPublishedContainer`, `cnaRejectedContainer`, `adpContainer` |
| Top level | `oneOf` of the published and rejected record shapes |
| Licence | ⚠ **CC0-1.0**, not MIT as PRD §6.5 states (more permissive; bundling is fine) |
| `dataVersion` constraint | `^5\.(0\|[1-9][0-9]*)(\.(0\|[1-9][0-9]*))?$`, `default: "5.2.0"` |

**Validator choice is settled by draft-07:** `opis/json-schema` 2.6.0 (Apache-2.0,
`php: ^7.4 \|\| ^8.0`) implements draft-06/07/2019-09/2020-12; `justinrainbow/json-schema` is
draft-04-centric. ⇒ **use `opis/json-schema`**.

Because `cnaPublishedContainer` is a named definition, the CNA-container-only shape validates
against `#/definitions/cnaPublishedContainer` in the same file — no second schema needed.

## Mapping (CVE 5.x → VEL)

See `Feed/CveRecordMapper.php`. CVSS and CWE are read from the CNA container first and from ADP
containers when the CNA left them out, with the provenance kept. Version entries become
inclusive ranges: `lessThanOrEqual` as is; `lessThan X` as `to = X` **and** fixed version `X`;
the single-string `1.0.0-15.0.0` split on the dash; a bare `version` as a single version.

The single-string style is not a legacy curiosity: **the Joomla CNA's own current records use
it** (`"version": "1.0-3.7.7"`), which settles D-6's "option to emit the string style" in
favour of having one.

## Runbook

- Options: *CVE Program feed* on, source, look-back days (first run and after gaps),
  *Include other CNAs' Joomla records*, *Create drafts for Joomla CNA records*.
  (The *GitHub token* option is removed — see GitHub rate limits above.)
- Scheduled task: `VEL - CVE Program Feed`, hourly, time budget 240 s (it resumes where it
  stopped).
- *Feed Runs* shows each pass; *Feed Items* the queue; the dashboard the pending count and the
  last run's status.
- A failed run leaves the window open so the next run covers it.

## Fixtures

`tests/fixtures/vel/cve/`:

| File | CVE | Exercises |
|---|---|---|
| `cve-joomla-cna-extension.json` | CVE-2026-105606 | current house style; `cna` + CISA-ADP; v4.0; `CWE-434` |
| `cve-joomla-cna-core.json` | CVE-2026-73337 | core; vendor `Joomla! Project`; `impacts[]` |
| `cve-joomla-cna-legacy.json` | CVE-2020-35610 | 2020 style: `problemTypes` `type:"text"` with no `cweId`; `x_refsource_*` tags |
| `cve-other-cna-extension.json` | CVE-2024-5736 | CERT-PL on a Joomla extension; **`dataVersion` 5.1** |
| `cve-rejected.json` | CVE-2026-73327 | REJECTED: `rejectedReasons`, `dateRejected`, no `affected` |
| `cvelist-deltalog-head.json` | — | 7 newest `deltaLog.json` entries, captured via `Range` (HTTP 206) |
| `cvelist-delta.json` | — | the live `delta.json`, 980 bytes — proof it is one window, not an hour |
