# The CVE Program feed (R-02, F-02)

## What it does

Every hour `vel.feed_cna` reads the public `CVEProject/cvelistV5` mirror's delta log, fetches each
record changed since the last run, maps it, classifies it and stores it in *Feed Items*:

| Classification | Rule | What happens |
|---|---|---|
| `joomla_cna` | `assignerShortName` (or `assignerOrgId`) is ours and the products are not Joomla core | a draft advisory is created automatically (option `feed_auto_create`), or offered for review if one with that CVE id exists |
| `joomla_core` | every affected product is Joomla itself | listed for the record; never an advisory |
| `joomla_extension_other_cna` | another CNA's record whose products, platforms, collection URL, title or description say "Joomla" | offered for review (option `feed_import_other_cnas`) |
| `unrelated` | none of the above | not stored |

A record is keyed by `(source, cve_id)` and hashed on its content minus `dateUpdated`: unchanged
records are no-ops; changed ones return to *pending* with the advisory still linked.

Alternative source (option `cna_source = cve_services`): the authenticated CVE Services list,
which returns the organisation's own records only.

## Mapping (CVE 5.x → VEL)

See `Feed/CveRecordMapper.php`. CVSS and CWE are read from the CNA container first and from ADP
containers (CISA Vulnrichment) when the CNA left them out, with the provenance kept. Version
entries become inclusive ranges: `lessThanOrEqual` as is; `lessThan X` as `to = X` **and** fixed
version `X`; the single-string `1.0.0-15.0.0` split on the dash; a bare `version` as a single
version.

## Runbook

- Options: *CVE Program feed* on, source, GitHub token (optional), look-back days (first run and
  after gaps), *Include other CNAs' Joomla records*, *Create drafts for Joomla CNA records*.
- Scheduled task: `VEL - CVE Program Feed`, hourly, time budget 240 s (it resumes where it stopped).
- *Feed Runs* shows each pass; *Feed Items* the queue; the dashboard the pending count and the
  last run's status.
- A failed run leaves the window open so the next run covers it.

## Research checklist (R-02) — confirm before go-live

- [ ] `https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/deltaLog.json` exists, its
      retention (believed ~30 days of hourly deltas) and entry shape (`fetchTime`, `new[]`,
      `updated[]` with `cveId`, `dateUpdated`). Adjust `CvelistClient` if the layout differs.
- [ ] Record URL layout `cves/<year>/<NNNxxx>/CVE-<year>-<NNNNN>.json` (`NNN` = id ÷ 1000).
- [ ] GitHub raw rate limits with and without a token.
- [ ] CVE Services: base URLs (`cveawg.mitre.org`, `cveawg-test.mitre.org`), the three auth
      headers, `POST /cve-id` parameters and response (`cve_ids[].cve_id`), `POST`/`PUT
      /cve/{id}/cna` body (`{"cnaContainer": …}`), `GET /cve` list filters and paging. Adjust
      `CveServicesClient`.
- [ ] Which record format version CVE Services accepts for the Joomla CNA today (5.1.x or 5.2);
      set `cve_data_version` accordingly.
- [ ] Download `schema/CVE_Record_Format.json` from `CVEProject/cve-schema` into
      `administrator/components/com_vel/schema/cve-record.json`; install `opis/json-schema`.
- [ ] The Joomla CNA's scope statement from the partner page, into this document.
- [ ] Fixtures into `tests/fixtures/vel/`: a record with an ADP container; a record from another
      CNA about a Joomla extension; a rejected record.
