# Install from Web (com_apps)

Joomla's administrator has an "Install from Web" tab (Extensions > Install). It lets a site
administrator browse the directory and install an extension without leaving the backend. This
document records how that feature talks to the directory today, and how `com_apps` answers the
same calls from the new JED, so that the only change needed on the day of the migration is DNS.

## How Joomla calls the directory

The tab is the core plugin `plg_installer_webinstaller` (Joomla 4, 5 and 6). The server it talks to is
a class constant, `WebInstaller::REMOTE_URL = https://appscdn.joomla.org/webapps/`, with no setting
to change it. DNS is therefore the only lever that reaches every installed site, and Joomla itself
never has to change.

The calls are made by JavaScript running in the administrator's browser, not by the site's PHP.
The directory answers with `Access-Control-Allow-Origin: *`.

Request, always a GET:

    /webapps/index.php?format=json&option=com_apps&view=<view>[&id=<n>]
        &product=<b64>&release=<major.minor>&dev_level=<b64>&list=grid|list&lang=<b64 tag>
        [&filter_search=<term>][&ordering=<key>][&filter_version=<v>]

| Parameter | Values |
|---|---|
| view | `dashboard` (home and search), `category` (with `id`), `extension` (with `id`) |
| ordering | empty (default), `num_reviews`, `score`, `core_title`, `core_created_time`, `core_modified_time` |
| filter_search | lowercased by the client, spaces turned into `_`, only `a-z 0-9 - _` kept |
| filter_version | `current` or a Joomla major version |
| product, dev_level, lang | base64 strings; `product` is "Joomla" |

Response:

    {"success":true,"message":null,"messages":null,"data":{"html":"...","pluginuptodate":-1}}

Only `data.html` is used. The client puts it in the page after running it through
`Joomla.sanitizeHtml`, so any tag or attribute outside its allow-list silently disappears.

### The markup contract

This is taken from `media/plg_installer_webinstaller/js/client.js` and `scss/client.scss` in
Joomla 6, not from the live server (see "Findings"). It is a Bootstrap 5 layout. The client needs:

- In **every** response, `#com-apps-searchbox`, `#search-extensions`, `#search-reset` and
  `#com-apps-ordering`. The client wires them up unconditionally after each response; if one is
  missing it throws, never removes its loading overlay, and the tab is stuck. This includes the
  extension page and the "no results" page.
- Category and breadcrumb links as `a.transcode` with an `href` like
  `index.php?option=com_apps&format=json&view=category&id=23`.
- Cards (`.grid-container .card .card-body`) and list rows (`.list-container .list-group-item .row`)
  opened by a click on an element with class `load-extension` and a `data-url` pointing at the
  extension view. Nothing inside a `.load-extension` may be an `a.transcode`: the client would
  handle the click twice, start two requests, and leave one loading overlay behind.
- The grid/list switch: `.grid-view`, `.list-view`, `#btn-grid-view`, `#btn-list-view`,
  `.grid-container`, `.list-container`.
- On the extension view, `#install-extension` with `data-downloadurl` (the developer's update
  server XML) and `data-name`, or `#install-extension-from-external` with `data-downloadurl`
  for an extension that is installed from the developer's own site.
- Only the tags and attributes of `Joomla.sanitizeHtml`'s allow-list: anything else is removed
  with its children (no `dl`, `table`, `label`, `nav`, `blockquote`, `style`, `onclick`), and the
  client's own list for `input` drops `value`, so a search term cannot be put back in the box.

`tests/unit/Apps/MarkupContractTest.php` checks all of this, and `tests/cypress/.../08-install-from-web.cy.js`
runs Joomla's real client against the component.

## Findings about the current server

Measured against the live endpoint on 2026-10-17.

- It is not cached. Every response carries `Cache-Control: no-store`, sets a session cookie, and
  Cloudflare reports `cf-cache-status: DYNAMIC`, so every click in every administrator reaches PHP
  on the origin.
- Responses are large: about 360 KB for the home page and for a category, 140 KB for a search.
  The home page and each category list 256 extensions at once, there is no pagination.
- The markup it sends is the old one (`onclick="Joomla.loadweb(...)"`, `span3` and `thumbnails`
  classes, no `#install-extension`) for every `release` value from 3.9 to 6.1. The Joomla 6
  client strips `onclick`, styles Bootstrap 5 classes, and calls `addEventListener` on elements
  that markup does not have, so the tab on current Joomla is probably already broken. This
  has not been checked against the live server in a browser. It does not change the plan: the
  markup contract is the client's.
- An unknown extension id returns `{"error":true,"message":"Attempt to assign property \"image\"
  on null"}`, and a malformed category id returns 200.
- The old Joomla 3 loader (`jedapps/webinstaller.js`) is a 404, so Joomla 3 is not supported.

## What com_apps does

`com_apps` is a small component, named so that Joomla routes `option=com_apps` to it with no
rewriting of the query string. It has no database table of its own: it reads the catalogue that
`com_jed` already maintains.

**Which extensions are listed.** Only what the public site would show:
`approved = 1 AND state = 1 AND blocked = 0 AND deleted = 0` (the rules in `ListingAccess`), and
only those whose `joomla_versions` include the client's Joomla major version (ids `60`/`61` for
Joomla 6). The listing therefore starts as short as the number of extensions that declare
Joomla 6, which is what the old directory did too; the sample data declares none.

**The install button.** An extension with `uses_updater = 1` and an `update_url` gets
`#install-extension` with that URL. One with only a `download_url` gets
`#install-extension-from-external`. One with neither gets a link to its JED listing only.

**Load.** The design assumes any Joomla administrator in the world can open the tab, so the
expensive path must be the rare one:

1. *Edge cache.* Responses are `Cache-Control: public` with `s-maxage` (1 hour by default,
   5 minutes for searches) and `stale-while-revalidate` / `stale-if-error`, no `Set-Cookie`, an
   `ETag` and `304` support, and `Vary: Accept-Encoding` only. The cache key is the query string, which the
   client always builds in the same order.
2. *Normalised keys.* `dev_level` is ignored, `release` is reduced to its major version, the
   search term is normalised, unknown parameters are dropped. Fewer distinct URLs, more hits.
3. *Origin cache.* The rendered JSON is kept in Joomla's cache (group `com_apps`, so System >
   Clear Cache lists it) with a lock, so a cold cache costs one query per URL, not one per
   request. Clear it after changing the markup.
4. *Cheap misses.* One indexed query per view (the table already has an index on
   `approved, state, blocked, deleted`), at most 256 rows, no joins for the listing, and no
   table of its own to keep in step with the catalogue.
5. *Bounded search.* Terms shorter than 2 characters are refused, longer than 64 are cut, `LIKE`
   wildcards are escaped.
6. *No session.* A guest request to Joomla normally writes a row to the session table and sets a
   cookie. The component drops the cookie and the session data, but the row is written earlier,
   by Joomla, unless "Session Metadata for Guest" is off in Global Configuration > System. Turn
   it off on the host that serves this (see the operations list).
7. *Graceful errors.* Bad input gets a short JSON error with a 4xx status and a short cache time.

Measured on a laptop (PHP 8.5, Apache, MySQL, 2,200 listings, warm cache, 20 concurrent):
about 400 responses per second for the 380 KB home page and 500 to 650 for the smaller ones,
p99 under 100 ms. A cold home page costs about 45 ms, a cold category or extension page 15 to
25 ms. With the CDN in front the origin should see a small fraction of that, but that is the
number to compare real traffic with. Run `tests/load/com_apps.sh` to repeat it.

Settings (Options of the component): origin cache lifetime, edge `s-maxage` for browsing and for
search, maximum rows per page, minimum search length.

## Instructions for the Joomla operations team

Nothing here changes Joomla core or the sites that use the feature.

1. Deploy the JED with `com_apps` installed.
2. On the JED, set "Session Metadata for Guest" to No (Global Configuration > System > Session),
   so that visitors who only call this endpoint do not each get a row in the session table.
   Make the JED answer for the host `appscdn.joomla.org` (certificate included) and rewrite
   `/webapps/index.php` to the JED's `index.php`, keeping the query string. With Apache:

       RewriteRule ^/webapps/index\.php$ /index.php [QSA,L]

   The JED is then reached at the same URLs as today. Nothing else under `/webapps/` is used.
3. Cloudflare: add a cache rule for `appscdn.joomla.org/webapps/*` that respects the origin
   `Cache-Control` and keeps the query string in the cache key. Add a rate limit on requests
   with `filter_search`. Purge the path when the catalogue changes a lot (a migration, say);
   otherwise the TTLs take care of it. The JED's own cache of rendered answers lives under
   System > Clear Cache, group `com_apps`.
4. Logos and thumbnails are currently on `extensionscdn.joomla.org`. Either keep that name
   pointing at the old storage or point it at the JED's media.
5. Cutover: test first with `curl --resolve appscdn.joomla.org:443:<new ip>` and with a Joomla 5
   and a Joomla 6 administrator using a hosts entry. Then switch DNS. Rollback is switching it
   back, because the old origin is untouched.

## Testing

- `tests/unit/Apps`: request normalisation and cache keys, the category tree, the HTTP headers,
  what the service answers, the markup contract (including the sanitizer allow-list and that
  every element `client.js` looks up exists) and a comparison with recorded live responses.
- `tests/fixtures/com_apps`: responses recorded from the live endpoint.
- `tests/cypress/integration/workflow/08-install-from-web.cy.js`: Joomla's own client, in a
  browser, browsing, searching, sorting and opening an extension. The request to
  appscdn.joomla.org is redirected to the local site with `cy.intercept`; nothing is installed.
- `tests/load/com_apps.sh`: a rough load check with ApacheBench.
