## Appendix D — External API research notes

**[VERIFIED R-01 / R-02 — 2026-11-05.]** This appendix was written from memory and is now
replaced with measurements. The NVD figures come from a complete census of the 1,633-record
`keywordSearch=Joomla` corpus; the CVE Program figures from the live mirror and the official
OpenAPI 2.9.2 specification. Narrative and design consequences:
[`VEL2-Feeds-PRD.md`](feeds/VEL2-Feeds-PRD.md). Operator documents: [`cve.md`](feeds/cve.md),
[`nvd.md`](feeds/nvd.md). Raw measurements: [`RESEARCH-PROGRESS.md`](feeds/RESEARCH-PROGRESS.md).
`[VERIFY R-03]` (CWE) and `[VERIFY R-04]` (legacy) below are **not** yet confirmed.

### NVD API 2.0 — confirmed

Base `https://services.nvd.nist.gov/rest/json/cves/2.0`. Parameters confirmed in use: `cveId`,
`keywordSearch`, `keywordExactMatch`, `virtualMatchString`, `lastModStartDate`/`lastModEndDate`,
`pubStartDate`/`pubEndDate`, `resultsPerPage`, `startIndex`, `noRejected`, and
**`sourceIdentifier`** (not previously listed — `security@joomla.org` returns 558 records).
`apiKey` is a request **header**.

| Limit | Measured |
|---|---|
| Window | **120 days**, enforced |
| `resultsPerPage` | **2000**, enforced |
| Rate | documented 5/30 s (no key), 50/30 s (key); **no rate-limit headers exist** |
| Date format | **lenient** — bare, `+00:00`, `Z`, `.000Z` all identical; missing offset = UTC |

⚠ **Errors are HTTP 404 with an empty body and the reason in a `message` response header.**
There is no JSON error body. This is the single most important client-implementation fact on
the NVD side.

⚠ **`virtualMatchString` with `target_sw=joomla` returns 13 records** against
`keywordSearch=Joomla`'s **1,633**. The control `target_sw=wordpress` returns **16,254**, so the
parameter works and the Joomla data simply does not exist. Tested, rejected, closed.

Response: `vulnerabilities[].cve` with `id`, `sourceIdentifier`, `published`, `lastModified`,
`vulnStatus`, `descriptions[]`, `metrics`, `weaknesses[]`, `configurations[]`, `references[]`.

| `metrics` container | Records | Primary | Secondary |
|---|---|---|---|
| `cvssMetricV2` | 1151 | 1146 | 5 |
| `ssvcV203` (**not CVSS**) | 473 | — | — |
| `cvssMetricV31` | 405 | 259 | 164 |
| `cvssMetricV40` | 375 | **0** | **375** |
| `cvssMetricV30` | 199 | 194 | 5 |

⚠ All v4.0 is Secondary, so "prefer Primary" discards it. Select **version first** (4.0 → 3.1 →
3.0, ignore v2), then Primary within the version.

`weaknesses[].description[].value`: real CWE ids 1,591; **`NVD-CWE-Other` 88 and
`NVD-CWE-noinfo` 53 are not ids** and must be filtered.

`configurations[].nodes[].cpeMatch[]` over 5,899 rows: `criteria`, `vulnerable`,
**`matchCriteriaId`** always present; `versionEndIncluding` 320, `versionStartIncluding` 241,
`versionEndExcluding` 234, `versionStartExcluding` 0 (handle anyway). **287 of 1,633 records
(17.6%) have no `configurations` key at all.**

`references[].tags` use NVD's own Title Case vocabulary, **not** the CVE 5.x list in §6.1.5 —
`Exploit` 2368, `Vendor Advisory` 1565, `Third Party Advisory` 1036, `VDB Entry` 640, `Patch`
341, `Product` 225, `Mailing List` 78, `Broken Link` 58, `Release Notes` 51, `URL Repurposed`
32, `Issue Tracking` 28, `US Government Resource` 8, `Not Applicable` 6, `Technical Description`
4, `Permissions Required` 4, `Mitigation` 2. `VDB Entry` and `URL Repurposed` have no CVE 5.x
equivalent.

`vulnStatus`: Modified 1189, **Deferred 263**, Analyzed 157, Awaiting Analysis 23, Rejected 1.

CPE 2.3 field order is as stated:
`cpe:2.3:part:vendor:product:version:update:edition:language:sw_edition:target_sw:target_hw:other`.
⚠ But the claim that "Joomla extensions typically carry `target_sw = joomla`" is **false** — 13
records do, corpus-wide. Core is spelled **both** `joomla:joomla\!` (3,301 rows) and
`joomla:joomla` (622 rows), and CPE vendor `joomla` also covers extensions (`rs_gallery2`,
`com_yvcomment`, `bsq_sitestats`, …), so the product test is mandatory.

### CVE Program — confirmed, with one major correction

CVE Services `https://cveawg.mitre.org/api/` (prod) and `https://cveawg-test.mitre.org/api/`
(test, separate dataset) — both live. Auth headers `CVE-API-ORG`, `CVE-API-USER`, `CVE-API-KEY`
confirmed, required per-operation.

| Endpoint | Status |
|---|---|
| `GET /cve/{id}` | ✔ **public, no auth** |
| `GET /cve`, `GET /cve_cursor` | ⚠ **requires the Secretariat or Bulk Download role — not available to the Joomla CNA.** The §6.6.1 "alternative source" built on this does not exist |
| `GET /cve-id` | ✔ "registered users"; filters `state`, `cve_id_year`, `time_reserved/modified.lt/gt`, `page`; 500/page. **This is the correct path to our own records**, paired with the public `GET /cve/{id}` |
| `POST /cve-id` | ✔ **query-string** params `amount`, `cve_year`, `short_name`, and **`batch_type`** when `amount > 1`. **206 = partial fulfilment**, not an error |
| `POST`/`PUT /cve/{id}/cna` | ✔ body `{"cnaContainer": …}`; minimum `descriptions[]` + `affected[]` + `references[]`; optional `?erlcheck`; 200 body `{valid, warnings[], message, created}`. `providerMetadata`, `datePublished`, `assignerShortName` are **server-set** |
| `POST`/`PUT /cve/{id}/reject`, `PUT /cve/{id}/adp`, `POST /cve-id-range/{year}`, `GET /org/{shortname}/id_quota`, `GET /health-check` | available for later phases |

**Record format:** `dataVersion` tracks the **assigner's last write, not the repository** —
records untouched since ~2025 are still `5.1` (CERT-PL, VulDB, INCIBE and mitre samples all
confirmed), everything written in 2026 is `5.2`. Emit `5.2`; parse `^5\.\d+(\.\d+)?$`.

**Schema:** `CVEProject/cve-schema` **v5.2.0 (2025-10-29)**, default branch `main`.
`schema/CVE_Record_Format.json` is 72,841 bytes, **draft-07**, **no external `$ref`s**, 46
definitions including `cnaPublishedContainer` / `cnaRejectedContainer` / `adpContainer`,
licensed **CC0-1.0** (⚠ not MIT, as §6.5 previously said). Draft-07 settles the validator:
**`opis/json-schema`**.

**Public bulk data:** `CVEProject/cvelistV5`.
`cves/<year>/<floor(sequence/1000)>xxx/CVE-<id>.json` — bucket **not** zero-padded; 6-digit
sequences work. `cves/deltaLog.json` is **37 MB**, newest-first, pretty-printed, rolling
**30.00 days**, updated every **~11 min** (p50; p90 30 min, max 229 min), ~4,130 changed
records/day, with `Accept-Ranges: bytes` + `ETag` + `max-age=300`. Entry keys `fetchTime`,
`numberOfChanges`, `new[]`, `updated[]`, `error[]`; item keys `cveId`, `dateUpdated`,
`cveOrgLink`, **`githubLink`** (the ready-made raw URL). ⚠ `cves/delta.json` is **980 bytes and
holds one ~11-minute window**, not "the latest hour". `raw.githubusercontent.com` is served by
Fastly, exposes **no rate-limit headers**, and is not governed by the GitHub REST API quota — a
token buys nothing.

**Joomla CNA:** orgId `6ff30186-7fb7-4ad9-be33-533e7b05e586` ✔, shortName `Joomla` ✔, `cnaID`
`CNA-2020-0036`, organizationName **`Joomla! Project`**, contact `security@joomla.org`, TLR
MITRE. Scope, verbatim: *"Core Joomla! CMS, the Joomla Framework, and Joomla! Extensions issues
only."* — **extensions are explicitly in scope.**

**[NEW] The CNA's output, by year:** 2020:7, 2021:24, 2022:16, 2023:24, 2024:6, 2025:43,
**2026:326** across 43 vendor domains. 315 of the 2026 records follow
`Joomla Extension - <vendor-domain> - <summary> in <product> <range>` (289) or
`Joomla! Core - [YYYYMMDD] - <summary> in Joomla <range>` (26). `x_generator.engine` is
`Vulnogram 0.1.0-dev` throughout. ADP containers are `CISA-ADP` carrying **SSVC, not CVSS**.
