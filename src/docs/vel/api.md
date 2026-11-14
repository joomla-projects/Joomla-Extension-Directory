# The public API, feeds and the legacy feed

Base: `https://extensions.joomla.org/vulnerable-extensions`. Only **published** advisories are
ever served; drafts are a 404 everywhere.

## JSON v2

| Endpoint | Method | Purpose |
|---|---|---|
| `/api/v2/index.json` | GET | Discovery: endpoints, filters, dataset ETag, limits, legacy-feed sunset |
| `/api/v2/advisories.json` | GET | Paged list; same filters as the HTML list |
| `/api/v2/advisories/{id}.json` | GET | One advisory by numeric id, `VEL-0042`, or `CVE-YYYY-NNNN` |
| `/api/v2/cve/CVE-YYYY-NNNN.json` | GET | The CVE Record 5.x document the VEL generates |
| `/api/v2/dataset.json` | GET | Every published advisory (and recently withdrawn ones, flagged) in one document |
| `/api/v2/check` | POST | Match a posted inventory |

Filters on the list: `status` (live \| patched \| abandoned \| all; default live), `search`,
`severity` (comma list), `cvss_min`, `cvss_max`, `vendor`, `type`, `element` (repeatable or comma
list), `cve`, `from`, `to`, `updated_since`, `include_withdrawn`, `sort` (published \| updated \|
title \| score), `dir`, `limit`, `limitstart`.

Every GET answers with `ETag`, `Cache-Control: public, max-age=…`, `Access-Control-Allow-Origin: *`
and honours `If-None-Match` with a 304. Errors are `{"error": {"code", "message"}}` with the right
status.

The advisory object is in the PRD, Appendix B.1. Identity, where known, is
`{type, element, folder, client_id}`; version ranges are inclusive, `*` unbounded; `fixed_version`
is the first safe version and anything at or above it is safe whatever the ranges say.

### Checking a site

Preferred: download `dataset.json` (a few hundred KB, cacheable, revalidated by ETag) and match
locally - the site's inventory never leaves it. `plg_system_velcheck` does this in its default
mode and documents the matching rule in its source.

Alternative: `POST /api/v2/check` with

```json
{ "client": "joomla/6.0.1 plg_system_velcheck/1.0",
  "extensions": [ { "type": "plugin", "element": "modals", "folder": "system", "client_id": 0, "version": "14.2.0" } ] }
```

Answer per entry: `match` = `affected` \| `not_affected` \| `unknown` (version not parsable,
product identity not yet known on the advisory), a `reason`, and the matching advisories with
`fixed_version` and URL. The inventory is not stored. At most 500 entries per call.

## RSS / Atom

`/vulnerable-extensions?format=feed&type=rss` (or `atom`), 50 newest, with the same filters:
`?format=feed&type=rss&status=live&severity=high,critical` is a feed of exactly that.

## The legacy feed

`/vulnerable-extensions/json-feed` reproduces the pre-VEL2 field set (one entry per product;
`category` Live VEL / Resolved VEL / Unresolved VEL; `risk_level`, `start_version`,
`vulnerable_version`, `patch_version`, `recommendation`, `update_notice`, `exploit_type`, `cve_id`,
…) and is sent with `Deprecation: true`, a `Link: rel="successor-version"` and, once the option is
set, `Sunset`. It answers 410 after the sunset date. Research task R-04 captures the live feed
and adjusts the field names byte for byte.

## Inbound webhook (optional)

`POST /vulnerable-extensions?task=inbound.receive` with the JSON shape in
`Mail/WebhookReceiver.php` and header `X-VEL-Signature: sha256=<HMAC of the raw body with the
configured secret>`. Only when the inbound mode is *Provider webhook*.
