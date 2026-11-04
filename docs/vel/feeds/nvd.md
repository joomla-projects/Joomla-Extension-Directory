# The NVD feed (R-01, F-03)

## What it does

`vel.feed_nvd` does two things:

1. **Discovery**: `keywordSearch=Joomla` over the window since the last run, post-filtered: Joomla
   core (CPE vendor `joomla`, product `joomla\!`) is classified core; anything whose CPE
   `target_sw` is `joomla` or whose vendor/product names say "Joomla" is an extension record;
   a record that merely mentions Joomla in its description is queued as *unclassified* for a
   human look.
2. **Enrichment**: for published/ready advisories with a CVE id and no CVSS or no CWE, fetches the
   NVD's record by id and queues it as a merge offer (NVD's own CVSS - *Primary* - is preferred
   over the CNA's *Secondary*; CPE version bounds become ranges; `versionEndExcluding` becomes the
   fixed version).

The NVD is secondary to the CVE Program feed: since early 2024 the NVD has a large enrichment
backlog and many recent records carry no CPE or CVSS for weeks.

## Rate limits and windows

5 requests per rolling 30 s without a key, 50 with one; the client sleeps 6.5 s / 1 s between
requests regardless and gives up on a 403/429 for the run. Windows may not exceed 120 days; the
source splits longer look-backs.

## Runbook

- Options: *NVD feed* on, API key (get one at nvd.nist.gov/developers), look-back days.
- Scheduled task: `VEL - NVD Feed`, every 4 hours, time budget 240 s.
- Enrichment is capped at 25 advisories per run and skips any looked up in the last 7 days.

## Research checklist (R-01) — confirm before go-live

- [ ] Endpoint `https://services.nvd.nist.gov/rest/json/cves/2.0` and the parameters used:
      `keywordSearch`, `lastModStartDate`, `lastModEndDate` (format `YYYY-MM-DDTHH:MM:SS.mmm`),
      `resultsPerPage`, `startIndex`, `cveId`; header `apiKey`.
- [ ] Whether `virtualMatchString` can express `target_sw = joomla` (would replace the keyword
      search with something far more precise). If so, add it to `NvdFeedSource`.
- [ ] The Joomla CNA's `sourceIdentifier` as the NVD spells it (believed `security@joomla.org`),
      so our own records can be recognised in discovery as well as by id.
- [ ] Response shape for `cvssMetricV31` / `cvssMetricV40` (`type` Primary/Secondary), `weaknesses`,
      `configurations[].nodes[].cpeMatch[]` bounds, `references[].tags`.
- [ ] Fixtures into `tests/fixtures/vel/`: a Joomla-extension CVE with CPE, one without, a core
      CVE, an unrelated keyword hit, a rejected CVE.
