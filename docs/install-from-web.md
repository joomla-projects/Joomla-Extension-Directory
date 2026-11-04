# Install from Web (com_apps)

Joomla's administrator has an "Install from Web" tab (Extensions > Install). It lets a site
administrator browse the directory and install an extension without leaving the backend. This
document records how that feature talks to the directory today for Joomla 3, 4, 5 and 6, and how
`com_apps` answers the same calls from the new JED, so that the only change needed on the day of
the migration is DNS.

## How Joomla calls the directory

The tab is the plugin `plg_installer_webinstaller`. The server it talks to is built in:
`https://appscdn.joomla.org/webapps/`, with no setting to change it. DNS is therefore the only
lever that reaches every installed site, and Joomla itself never has to change.

| Joomla | Where the plugin comes from | Script | How it asks |
|---|---|---|---|
| 3.x | Not in core. The installer shows a button that installs it from `https://appscdn.joomla.org/webapps/jedapps/webinstaller.xml`, which points at version 2.1.2 (3.9, 3.10) or older ones, hosted on downloads.joomla.org | jQuery | JSONP, callback `jedapps_jsonpcallback`; puts the answer in the page as it is |
| 4.0 to 4.4 | Core | plain JS | `Joomla.request` (XHR) GET, JSON; runs the answer through `Joomla.sanitizeHtml` |
| 5.x, 6.x | Core | plain JS | same as 4.x (the scripts differ in two keyboard-handling lines) |

All of them call the endpoint from the administrator's browser, not from the site's PHP. Request,
always a GET:

    /webapps/index.php?format=json&option=com_apps&view=<view>[&id=<n>]
        &product=<b64>&release=<b64 major.minor>&dev_level=<b64>&list=grid|list&lang=<b64 tag>
        [&filter_search=<term>][&ordering=<key>][&filter_version=<v>]
        [&pv=<b64 plugin version>&callback=jedapps_jsonpcallback]      (Joomla 3 only)

| Parameter | Values |
|---|---|
| view | `dashboard` (home and search), `category` (with `id`), `extension` (with `id`) |
| release | **base64** of the Joomla version, `Ni4y` is 6.2. This is what decides the markup |
| ordering | empty (default), `num_reviews`, `score`, `core_title`, `core_created_time`, `core_modified_time` |
| filter_search | lowercased by the client, spaces turned into `_`, only `a-z 0-9 - _` kept |
| filter_version | `current`, `all`, or a Joomla major version |
| product, dev_level, lang, list, pv | not used by the answer |

Response:

    {"success":true,"message":null,"messages":null,"data":{"html":"...","pluginuptodate":-1}}

For Joomla 3 the same JSON is wrapped as `jedapps_jsonpcallback({...})` and served as
`application/javascript`. `data.html` is the page. `data.pluginuptodate` follows the `pv` parameter
(base64 plugin version): 1 from 2.1.2, 0 from 1.0.5, otherwise -1. Joomla 3's plugin shows an update
notice for anything below 1. Joomla 4 and up ignore it.

### Two markups, chosen by the release

The live directory looks at the decoded release: below 4 (and anything it cannot read) it sends the
old Bootstrap 2 markup, from 4 on the Bootstrap 5 one. `com_apps` does the same
(`AppsRequest::FIRST_BOOTSTRAP_RELEASE`). Recorded examples of both are in
`tests/fixtures/com_apps`.

**Joomla 4, 5, 6** (`AppsRenderer`), from `client.js` of the core plugin and the sanitizer:

- In **every** response, `#com-apps-searchbox`, `#search-extensions`, `#search-reset` and
  `#com-apps-ordering`. The client wires them up unconditionally after each response; if one is
  missing it throws, never removes its loading overlay, and the tab is stuck. This includes the
  extension page and the "no results" page.
- Category and breadcrumb links as `a.transcode` with an `href` like
  `index.php?option=com_apps&format=json&view=category&id=23`.
- Cards (`.grid-container .card .card-body`) and list rows opened by a click on an element with class
  `load-extension` and a `data-url` pointing at the extension view. Nothing inside a
  `.load-extension` may be an `a.transcode`: the client would handle the click twice, start two
  requests, and leave one loading overlay behind.
- `.grid-view`, `.list-view`, `#btn-grid-view`, `#btn-list-view`, `.grid-container`,
  `.list-container`, and optionally `#com-apps-filter-joomla-version` (`all` / `current`).
- On the extension view, `#install-extension` with `data-downloadurl` (the developer's update
  server XML) and `data-name`, or `#install-extension-from-external` with `data-downloadurl`.
- Only the tags and attributes of `Joomla.sanitizeHtml`'s allow-list: anything else is removed with
  its children (no `dl`, `table`, `label`, `blockquote`, `style`, `onclick`; `title` is stripped
  from `span`), and the client's own list for `input` drops `value`.

**Joomla 3** (`AppsLegacyRenderer`), from the 2.1.2 script and the old directory's output: nothing
is sanitised, and the page calls back into the script through inline handlers.

- `onclick="Joomla.loadweb(apps_base_url+'index.php?option=com_apps&format=json&view=extension&id=80');"`
  on a card, `onclick="return Joomla.installfromweb('<update server url>', '<name>')"` on the install
  button, `onclick="Joomla.apps.initiateSearch();"` on the search button.
- `#com-apps-searchbox` (which keeps the search term), `#search-reset`, `#com-apps-ordering`,
  `.grid-view`, `.list-view`, `a.transcode` links, `.grid-container` and `.list-container`.
- Every value that reaches a handler is an integer or an http(s) URL without quotes; a name is
  escaped for a JavaScript string and then for the attribute.
- An extension that is installed from the developer's own site gets a plain link: the script's
  `Joomla.installfromwebexternal()` refers to an undefined variable.

`tests/unit/Apps/MarkupContractTest.php` and `LegacyMarkupContractTest.php` check all of this
against the scripts themselves, and `FixtureParityTest.php` against the recorded live responses.

## Findings about the current server

Measured against the live endpoint on 2026-10-17.

- It is not cached. Every response carries `Cache-Control: no-store`, sets a session cookie, and
  Cloudflare reports `cf-cache-status: DYNAMIC`, so every click in every administrator reaches PHP
  on the origin.
- Responses are large: about 290 KB for the home page of Joomla 4 and later and 360 KB for Joomla 3, the grid and the list view both in the answer,
  140 KB for a search. The home page and each category list 128 extensions at once, with no
  pagination.
- An unknown extension id returns `{"error":true,"message":"Attempt to assign property \"image\"
  on null"}`, and a malformed category id returns 200.
- A JSONP callback is echoed after other characters are stripped from it (`alert(1)` comes back as `alert1`).
- Joomla 3 depends on one more thing outside `/webapps/index.php`: the plugin is installed from
  `/webapps/jedapps/webinstaller.xml` (a static file, last modified 2021, pointing at zips on
  downloads.joomla.org). `/webapps/jedapps/webinstaller.js` and `.css` return 404 and are not used
  by the 2.1.2 plugin.

## What com_apps does

`com_apps` is a small component, named so that Joomla routes `option=com_apps` to it with no
rewriting of the query string. It has no database table of its own: it reads the catalogue that
`com_jed` already maintains.

**Which extensions are listed.** Only what the public site shows:
`approved = 1 AND state = 1 AND blocked = 0 AND deleted = 0` (the rules in `ListingAccess`), and
only those whose `joomla_versions` include the client's Joomla major version (ids `30`, `40`,
`50`/`51`, `60`/`61`), unless the client asks for all versions. The listing therefore starts as
short as the number of extensions that declare Joomla 6; the sample data declares none.

**The install button.** An extension with `uses_updater = 1` and an `update_url` gets the install
button with that URL. One with only a `download_url` gets the "install from the developer's site"
button. One with neither gets a link to its JED listing only.

**JSONP.** For Joomla 3, `callback` is accepted when it is `jedapps_jsonpcallback` or what jQuery
generates (`jQuery` followed by digits and underscores). Anything else is a 400, which is stricter
than the live directory: the endpoint cannot be made to call another function of the page.

**Load.** The design assumes any Joomla administrator in the world can open the tab, so the
expensive path must be the rare one:

1. *Edge cache.* Responses are `Cache-Control: public` with `s-maxage` (1 hour by default,
   5 minutes for searches) and `stale-while-revalidate` / `stale-if-error`, no `Set-Cookie`, an
   `ETag` and `304` support, and `Vary: Accept-Encoding` only. The cache key is the query string,
   which the clients always build in the same order.
2. *Normalised keys.* `dev_level`, `lang`, `list`, `pv`, `product` and unknown parameters are
   ignored, `release` is reduced to its major version, the search term is normalised. Fewer
   distinct keys, more hits.
3. *Origin cache.* The rendered JSON is kept in Joomla's cache (group `com_apps`, so System >
   Clear Cache lists it) with a lock, so a cold cache costs one query per URL, not one per request.
   Clear it after changing the markup.
4. *Cheap misses.* One indexed query per view (the table already has an index on
   `approved, state, blocked, deleted`), at most 128 rows, no joins for the listing, and no table of
   its own to keep in step with the catalogue.
5. *Bounded search.* Terms shorter than 2 characters are refused, longer than 64 are cut, `LIKE`
   wildcards are escaped.
6. *No session.* A guest request to Joomla normally writes a row to the session table and sets a
   cookie. The component drops the cookie and the session data, but the row is written earlier, by
   Joomla, unless "Session Metadata for Guest" is off in Global Configuration > System. Turn it off
   on the host that serves this (see the operations list).
7. *Graceful errors.* Bad input gets a short JSON error with a 4xx status and a short cache time.

Measured on a laptop (PHP 8.5, Apache, MySQL, 2,200 listings, warm cache, 20 concurrent): about 500
responses per second for the 190 KB home page and 550 to 650 for the smaller ones, p99 under 100 ms.
A cold home page costs about 40 ms, a cold category or extension page 15 to 30 ms. With the CDN in
front the origin should see a small fraction of that, but that is the number to compare real traffic
with. Run `tests/load/com_apps.sh` to repeat it.

Settings (Options of the component): origin cache lifetime, edge `s-maxage` for browsing and for
search, maximum rows per page, minimum search length.

## Instructions for the Joomla operations team

Nothing here changes Joomla core or the sites that use the feature.

1. Deploy the JED with `com_apps` installed.
2. On the JED, set "Session Metadata for Guest" to No (Global Configuration > System > Session), so
   that visitors who only call this endpoint do not each get a row in the session table. Make the
   JED answer for the host `appscdn.joomla.org` (certificate included) and rewrite
   `/webapps/index.php` to the JED's `index.php`, keeping the query string. With Apache:

       RewriteRule ^/webapps/index\.php$ /index.php [QSA,L]

3. **Keep serving the static file `/webapps/jedapps/webinstaller.xml`** (and nothing else of
   `/webapps/jedapps/` is needed). Joomla 3 sites install their Install from Web plugin from it, and
   without it a Joomla 3 administrator can no longer add the tab. Copy it from the current origin
   before the switch; it points at zips on downloads.joomla.org, which are not part of this change.
4. Cloudflare: add a cache rule for `appscdn.joomla.org/webapps/*` that respects the origin
   `Cache-Control` and keeps the query string in the cache key. Add a rate limit on requests with
   `filter_search`. Purge the path when the catalogue changes a lot (a migration, say); otherwise the
   TTLs take care of it. The JED's own cache of rendered answers lives under System > Clear Cache,
   group `com_apps`.
5. Logos and thumbnails are currently on `extensionscdn.joomla.org`. Either keep that name pointing at
   the old storage or point it at the JED's media (the JED stores logo paths, which the component
   turns into absolute URLs on the JED's own host).
6. Cutover: test first with `curl --resolve appscdn.joomla.org:443:<new ip>` and with a Joomla 5 and a
   Joomla 6 administrator using a hosts entry (the Joomla 3 case is covered by the check below).
   Then switch DNS. Rollback is switching it back, because the old origin is untouched.

## Testing

What was run, and against what:

| Client | How | Result |
|---|---|---|
| Joomla 6.2 | Cypress, the real plugin script in a browser | passes |
| Joomla 5.4 | the same spec against a local Joomla 5.4 site | passes |
| Joomla 4.x | not run: its script is the Joomla 5 one apart from two keyboard-handling lines, and the live directory sends it the same markup | not run directly |
| Joomla 3.9, 3.10 | the 2.1.2 plugin script with Joomla 3.10's jQuery, Migrate and core.js, in jsdom (Joomla 3 itself does not run on current PHP) | passes |

- `tests/unit/Apps`: request normalisation (release, callback, version filter) and cache keys, the
  category tree, the HTTP headers including JSONP, what the service answers for each generation, both
  markup contracts (the sanitizer allow-list, every element the scripts look up, every inline
  handler) and a comparison with recorded live responses.
- `tests/fixtures/com_apps`: responses recorded from the live endpoint for both generations, and the
  Joomla 3 plugin script.
- `tests/cypress/integration/workflow/08-install-from-web.cy.js`: Joomla's own client in a browser,
  browsing, searching, sorting and opening an extension. The request to appscdn.joomla.org is
  redirected to the site under test with `cy.intercept`; nothing is installed. With `--config
  baseUrl=<a Joomla 5 site> --env appsUrl=<site with com_apps>` it runs another release's client.
- `tests/js/j3-client/run.sh <site url>`: the Joomla 3 run described above.
- `tests/load/com_apps.sh`: a rough load check with ApacheBench.
