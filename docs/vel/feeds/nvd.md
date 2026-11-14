# The NVD feed (R-01, F-03)

**Status: R-01 complete.** Every statement below was measured against the live NVD API 2.0 on
2026-11-05. Where a measurement contradicts the PRD, the contradiction is called out so the
reviewer can see what changed. Raw measurements: [`RESEARCH-PROGRESS.md`](RESEARCH-PROGRESS.md).
Design consequences: [`VEL2-Feeds-PRD.md`](VEL2-Feeds-PRD.md).

## What it does

`vel.feed_nvd` does two things:

1. **Discovery**: `keywordSearch=Joomla` over the window since the last run, then classified
   (§Classification below). The NVD is *not* the primary discovery source — the CVE Program feed
   is — but it catches records from CNAs whose Joomla-ness is only visible after NVD analysis.
2. **Enrichment**: for published/ready advisories with a CVE id and no CVSS or no CWE, fetches
   the NVD's record by id and queues it as a merge offer.

The NVD is secondary to the CVE Program feed. The enrichment backlog is not a rumour: of the 326
records the Joomla CNA published in 2026, **only 94 carry any CPE data**, and the modal
`vulnStatus` for them is `Deferred` (209). See *Enrichment backlog*, below.

## Endpoint and parameters — confirmed

Base `https://services.nvd.nist.gov/rest/json/cves/2.0`. Parameters used by `NvdFeedSource`:

| Parameter | Confirmed | Note |
|---|---|---|
| `cveId` | ✔ | single-record fetch, used by enrichment |
| `keywordSearch` | ✔ | `Joomla` returns **1,633** records |
| `lastModStartDate` / `lastModEndDate` | ✔ | **both or neither** — see error handling |
| `resultsPerPage` | ✔ | max **2000**; 2001 is an error |
| `startIndex` | ✔ | |
| `sourceIdentifier` | ✔ | **not in the PRD's list.** `security@joomla.org` → 558 records NVD-wide |
| `virtualMatchString` | ✔ mechanism works | **tested and rejected as the discovery query** — see below |
| `apiKey` | ✔ | request **header**, not a query parameter |

**Date format is lenient.** All four of these return byte-identical results
(`totalResults` 45 for 2026-11-01):

```
lastModStartDate=2026-11-01T00:00:00.000
lastModStartDate=2026-11-01T00:00:00.000+00:00
lastModStartDate=2026-11-01T00:00:00Z
lastModStartDate=2026-11-01T00:00:00.000Z
```

A missing offset is read as UTC. The client emits the `…Z` form so the intent is visible in the
URL and in logs.

**Window limit is 120 days and is enforced.** A 104-day window returns 200 (93,846 results);
a 153-day window is rejected. The source splits longer look-backs into ≤120-day slices.

### ⚠ Error handling — the one thing that will break a naive client

**NVD reports parameter errors as HTTP 404 with an empty body and the reason in a `message`
HTTP response header.** Measured:

| Request | Response |
|---|---|
| 153-day window | `404` + `message: Date range cannot exceed 120 days.` |
| `resultsPerPage=2001` | `404` + `message: resultsPerPage parameter cannot exceed 2000.` |
| `lastModStartDate` with no `lastModEndDate` | `404` + `message: Both lastModStartDate and lastModEndDate are required when either is present.` |

There is no JSON error body. A client that parses the body, or that treats 404 as "not found,
skip this one", will silently mis-handle every malformed request. `NvdClient` must read the
`message` response header on any non-200 and must not conflate *404-with-message* (our bug) with
*404-no-such-CVE* (ordinary).

## Rate limits

Documented: **5 requests per rolling 30 s** without a key, **50 with**. Those remain the design
basis. Observed enforcement is lenient — a burst of ten back-to-back unauthenticated `?cveId=`
requests all returned 200 — but the documented figures are what we honour.

**There are no rate-limit headers.** No `X-RateLimit-*`, no `Retry-After`. Backoff cannot be
header-driven: the client sleeps 6.5 s (no key) / 1 s (key) between requests unconditionally and
treats a 403 as the trip signal, abandoning the run and leaving the window open.

## Response shape — measured over the complete 1,633-record Joomla corpus

One request with `resultsPerPage=2000` returns all 1,633 keyword hits in 5.3 MB, so the figures
below are a census, not a sample.

### Metrics — five containers, not two

| Container | Records | Primary | Secondary |
|---|---|---|---|
| `cvssMetricV2` | 1151 | 1146 | 5 |
| `ssvcV203` | 473 | — (no `type` field) | — |
| `cvssMetricV31` | 405 | 259 | 164 |
| `cvssMetricV40` | 375 | **0** | **375** |
| `cvssMetricV30` | 199 | 194 | 5 |

⚠ **"Prefer Primary" would discard every CVSS 4.0 score.** `cvssMetricV40` is Secondary in
375 of 375 cases — NVD does not produce its own v4.0 analysis, so all v4.0 comes from CNAs
(including ours: every 2026 Joomla CNA record scores v4.0 only). The selection rule is therefore
**version first, then type**:

```
4.0 → 3.1 → 3.0        (ignore v2 entirely)
  within the chosen version: Primary before Secondary
```

⚠ `cvssMetricV30` (199 records) exists and must either be mapped or explicitly ignored.
⚠ `ssvcV203` (473 records) is a **non-CVSS** entry under `metrics` — CISA SSVC decision points.
The parser must not assume every key under `metrics` is a CVSS block.

### Weaknesses

`weaknesses[].type` is `Primary`/`Secondary`; the id is at `description[].value`:

| Value | Count |
|---|---|
| a real `CWE-…` id (Primary) | 1074 |
| a real `CWE-…` id (Secondary) | 517 |
| `NVD-CWE-Other` | 88 |
| `NVD-CWE-noinfo` | 53 |

⚠ `NVD-CWE-Other` and `NVD-CWE-noinfo` are **not CWE ids** and would fail
`#__vel_advisory_cwes.cwe_id` validation. 141 occurrences in this corpus; filter them out
explicitly rather than relying on a regex accident.

### Configurations

`configurations[].nodes[].cpeMatch[]` keys across 5,899 rows:

| Key | Rows |
|---|---|
| `criteria`, `vulnerable`, `matchCriteriaId` | 5899 (always present) |
| `versionEndIncluding` | 320 |
| `versionStartIncluding` | 241 |
| `versionEndExcluding` | 234 |

`versionStartExcluding` exists in NVD generally but does not occur in this corpus; handle it
anyway. `matchCriteriaId` is always present and is not mentioned in the PRD — it is the stable
key for a CPE match and is worth storing with an imported range.

⚠ **287 of 1,633 records (17.6%) have no `configurations` key at all.** The classifier and the
enrichment mapper must both treat its absence as normal.

### References — ⚠ NVD uses its own tag vocabulary

NVD tags are Title Case and are **not** the CVE 5.x lowercase vocabulary that PRD §6.1.5 defines.
Complete for this corpus:

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

`NvdRecordMapper` carries this table. `VDB Entry` and `URL Repurposed` have no CVE 5.x
equivalent: the reference is kept, the tag is dropped.

### `vulnStatus`

Modified 1189, **Deferred 263**, Analyzed 157, Awaiting Analysis 23, Rejected 1. `Deferred`
means NVD has stopped enriching the record — it is a permanent state for a large slice of our
own corpus and the PRD does not mention it. Rejected records carry no `configurations` and no
`weaknesses`, and `descriptions[0].value` begins `"Rejected reason: "`.

## Classification

⚠ **The PRD's CPE-based core/extension split does not work on modern records.** Records with CPE
vendor `joomla`, by publication year:

```
2005:4 2006:53 2007:58 2008:117 2009:143 2010:210 2011:73 2012:43 2013:15
2014:14 2015:13 2016:6 2017:19 2018:24 2019:27 2020:39 2021:28 2022:13
2023:5  2024:0  2025:0  2026:27
```

The 2024–2025 hole is the enrichment backlog; the 2026 figure is the core batch only. A rule that
needs CPE is a rule that fires on records from fifteen years ago.

Two further CPE corrections:

- ⚠ **Core is spelled two ways.** Under CPE vendor `joomla`: product `joomla\!` 3301 rows,
  product **`joomla`** 622 rows. A rule matching only `joomla\!` misses 622 rows.
- ⚠ **CPE vendor `joomla` is not proof of core.** The same vendor carries `rs_gallery2` (41),
  `com_yvcomment` (16), `bsq_sitestats` (8), `com_user` (6), `com_eventing` (6),
  `ignitegallery` (4) — all **extensions**. The product test is mandatory.

The rule `NvdFeedSource` implements, in order:

1. `sourceIdentifier == security@joomla.org` → ours. Then split on the description prefix
   (see `cve.md` — `Joomla! Core - [YYYYMMDD] -` vs `Joomla Extension - <vendor> -`), falling
   back to the CPE test below for pre-2026 records.
2. CPE vendor `joomla` **and** product ∈ {`joomla\!`, `joomla`} → `joomla_core`.
3. Any CPE with `target_sw == joomla` → `joomla_extension`. High confidence, low recall (13
   records corpus-wide), so it confirms rather than decides.
4. Description matches `/joomla/i` **and** `/(extension|component|module|plugin|template)/i`
   → `joomla_extension`.
5. Otherwise `unclassified` — queued for a human, not discarded. The fixture
   `nvd-unrelated-keyword-hit.json` (CVE-2024-32788, "FG Joomla to WordPress") is the canonical
   false positive: the product name contains "Joomla" but the vulnerable software is a
   **WordPress** plugin.

### `virtualMatchString` — tested, rejected, do not re-open

`GET …?virtualMatchString=cpe:2.3:a:*:*:*:*:*:*:*:joomla:*:*` returns HTTP 200 and the
`target_sw` filter genuinely applies. The mechanism works; the data does not exist:

| Query | `totalResults` |
|---|---|
| `virtualMatchString` `target_sw = joomla` | **13** |
| `virtualMatchString` `target_sw = wordpress` (control) | **16,254** |
| `virtualMatchString` vendor `joomla` | 1,002 |
| `keywordSearch=Joomla` | **1,633** |

The WordPress control proves the parameter is not broken — NVD simply does not tag Joomla
extension CPEs with `target_sw=joomla`. Adopting it would cut recall from 1,633 to 13. All
thirteen are `huge-it` (6), `jextn` (1), `chronoengine` (3), `joomlaux` (3).

**Decision: `keywordSearch=Joomla` remains the discovery query.** `target_sw=joomla` is retained
as classifier rule 3.

## Enrichment

For every published advisory with a `cve_id` and a missing CVSS or CWE, fetch `?cveId=` and offer:

- **CVSS** by the version-first rule above, provenance recorded.
- **CWE** from `weaknesses[]`, excluding `NVD-CWE-Other` / `NVD-CWE-noinfo`.
- **Version bounds** from `cpeMatch`: `versionStartIncluding` → `version_from`;
  `versionEndIncluding` → `version_to`; `versionEndExcluding` → the **fixed version**, and
  `version_to` only when the editor confirms the decrement.
- **References** with tags mapped through the table above.

Capped at 25 advisories per run; an advisory looked up within the last 7 days is skipped.

### Enrichment backlog — quantified

| Measure | Value |
|---|---|
| Joomla CNA records published 2026 | 326 |
| …with any `configurations` | **94** |
| …`Deferred` | 209 |
| …`Awaiting Analysis` | 23 |
| …`Analyzed` | 89 |
| Records in the whole corpus with no `configurations` | 287 / 1,633 (17.6%) |
| `security@joomla.org` records with any `joomla:joomla*` CPE | 75 / 446 |

Expect enrichment to come back empty for most recent records. That is the NVD's state, not a bug
in the feed, and the *Feed items* view should say so rather than showing a silent no-op.

## Runbook

- Options: *NVD feed* on, API key (free, from `https://nvd.nist.gov/developers/request-an-api-key`),
  look-back days.
- Scheduled task: `VEL - NVD Feed`, every 4 hours, time budget 240 s.
- A failed run leaves its window open so the next run covers it.

## Fixtures

`tests/fixtures/vel/nvd/`, each wrapped in the genuine NVD 2.0 response envelope:

| File | CVE | Exercises |
|---|---|---|
| `nvd-extension-with-cpe.json` | CVE-2025-2126 | `target_sw=joomla`; all four metric containers plus `ssvcV203`; two weaknesses; CPE bounds |
| `nvd-extension-no-cpe.json` | CVE-2026-105606 | Joomla CNA house style; `Deferred`; **v4.0 Secondary only**; no `configurations` |
| `nvd-core.json` | CVE-2026-73337 | core; `Analyzed`; `cpe:2.3:a:joomla:joomla\!` |
| `nvd-unrelated-keyword-hit.json` | CVE-2024-32788 | false positive — a WordPress plugin named "FG Joomla to WordPress" |
| `nvd-rejected.json` | CVE-2026-73327 | `vulnStatus: Rejected`; no `configurations`/`weaknesses`; `"Rejected reason: "` |
