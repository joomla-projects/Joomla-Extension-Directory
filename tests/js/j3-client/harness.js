// Runs Joomla 3's own Install from Web script (plg_installer_webinstaller 2.1.2, the version the
// directory offers to Joomla 3.9 and 3.10), with Joomla 3.10's jQuery, jQuery Migrate and core.js, in
// jsdom, against a com_apps endpoint. The client reaches the endpoint over JSONP, as it does in a
// browser. Nothing is installed; the run stops at the confirmation form.
//
//   tests/js/j3-client/run.sh http://localhost:8080/jed_j6/
const fs = require('fs');
const path = require('path');
const { JSDOM } = require('jsdom');

const vendor = path.join(__dirname, 'vendor');
const endpoint = process.argv[2];             // e.g. http://localhost:8080/jed_j6/
const read = (f) => fs.readFileSync(f, 'utf8').replace(/<\/script/gi, '<\\/script');
const b64 = (s) => Buffer.from(s).toString('base64');

const options = {
  base_url: endpoint, installat_url: b64('http://localhost/administrator/index.php?option=com_installer&view=install'),
  installfrom_url: '', product: b64('Joomla'), release: b64('3.10'), dev_level: b64('12'), installfromon: 0,
  language: b64('en-GB'), is_hathor: 0, pv: b64('2.1.2'),
};

const page = `<!doctype html><html><body>
<form id="adminForm"><input type="hidden" name="installtype"><input type="hidden" name="task"><input type="hidden" name="install_directory">
<input type="text" id="install_url" name="install_url"><input name="filter_search">
<div id="myTabContent"><div id="web">
<div id="jed-container" class="tab-pane"><div class="well" id="web-loader"><h2>Loading</h2></div>
<div class="alert alert-error" id="web-loader-error" style="display:none">error</div></div>
<fieldset class="uploadform" id="uploadform-web" style="display:none" dir="ltr"><div class="control-group">
<span id="uploadform-web-name-label">Name:</span> <span id="uploadform-web-name"></span><br>URL: <span id="uploadform-web-url"></span></div></fieldset>
</div></div></form>
<script type="application/json" class="joomla-script-options new">${JSON.stringify({ plg_installer_webinstaller: options })}</script>
<script>${read(path.join(vendor, 'jquery.min.js'))}</script><script>${read(path.join(vendor, 'jquery-migrate.min.js'))}</script>
<script>${read(path.join(vendor, 'core.js'))}</script>
<script>window.Joomla = window.Joomla || {}; Joomla.JText = Joomla.JText || { _: function (k) { return k; } }; Joomla.loadingLayer = function () {};</script>
<script>${read(path.join(__dirname, '..', '..', 'fixtures', 'com_apps', 'j3-client-2.1.2.js'))}</script>
</body></html>`;

const errors = [];
const dom = new JSDOM(page, {
  url: 'http://localhost:8080/j3harness/administrator/index.php', runScripts: 'dangerously', resources: 'usable', pretendToBeVisual: true,
  beforeParse(window) {
    window.addEventListener('error', (e) => errors.push(String(e.message)));
    window.alert = (m) => errors.push('alert: ' + m);
    window.confirm = () => true;
  },
});
const { window } = dom;
const $ = () => window.jQuery;
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
async function until(label, test, ms = 8000) {
  const start = Date.now();
  while (Date.now() - start < ms) { try { if (test()) return; } catch (e) { /* not yet */ } await sleep(50); }
  throw new Error('timed out waiting for: ' + label + (errors.length ? ' | page errors: ' + errors.join('; ') : ''));
}
const results = [];
const ok = (name) => { results.push(name); console.log('  ok  ' + name); };

(async () => {
  await until('scripts loaded', () => window.Joomla && window.Joomla.apps && window.jQuery);
  window.Joomla.apps.initialize();

  await until('home page of the directory', () => $()('#jed-container .com-apps-container .item').length > 3);
  if (!$()('#com-apps-searchbox').length || !$()('#search-reset').length || !$()('#com-apps-ordering').length) throw new Error('toolbar missing');
  if ($()('.j-jed-message').length) throw new Error('the client shows its plugin update notice');
  ok('home page rendered by the J3 client: ' + $()('#jed-container .item').length + ' cards');

  const hasOnclick = $()('#jed-container .thumbnail > div[onclick]').first();
  hasOnclick[0].click();
  await until('extension page after a card click', () => $()('#jed-container .full-item-container h2').length === 1);
  ok('a card click (Joomla.loadweb inline handler) opened: ' + $()('#jed-container h2 span').first().text());

  const install = $()('#jed-container a.install');
  if (install.length) {
    const expected = install.attr('onclick').match(/installfromweb\('([^']+)'/)[1];
    install[0].click();
    await until('install confirmation', () => $()('#uploadform-web-url').text() === expected && $()('#install_url').val() === expected);
    ok('Install button handed the update server to the installer form: ' + expected);
    if ($()('#uploadform-web').css('display') === 'none') throw new Error('confirmation form not shown');
    window.Joomla.installfromwebcancel();
  } else { console.log('  --  this listing has no update server'); }

  // A category link (a.transcode, wired by clickforlinks with .live)
  await until('category links', () => $()('#jed-container a.transcode').length > 3);
  const cat = $()('#jed-container .com-apps-sidebar a.transcode').eq(1);
  const catName = cat.text();
  cat.trigger('click');
  await until('category page', () => $()('#jed-container .com-apps-sidebar li.active a.selected').text() === catName);
  ok('a category link (a.transcode) loaded: ' + catName);

  // Search through the button's inline handler
  $()('#com-apps-searchbox').val('backup');
  $()('#jed-container button[onclick*="initiateSearch"]')[0].click();
  await until('search results', () => $()('#com-apps-searchbox').val() === 'backup' && $()('#jed-container .item').length > 0);
  ok('search: ' + $()('#jed-container .item').length + ' results, term kept in the box');

  $()('#com-apps-searchbox').val('qqqzzzxxx');
  window.Joomla.apps.initiateSearch();
  await until('empty search', () => /Cannot find any extensions/.test($()('#jed-container').text()));
  ok('an empty search says so');

  // Sorting (select change, .live)
  $()('#com-apps-searchbox').val('');
  window.Joomla.apps.initiateSearch();
  await until('dashboard again', () => $()('#jed-container .item').length > 3);
  const sel = $()('#com-apps-ordering');
  sel.prop('selectedIndex', 2).trigger('change');
  await until('sorted', () => $()('#com-apps-ordering').prop('selectedIndex') === 2 && $()('#jed-container .item').length > 3);
  ok('sorting by the select works');

  // grid / list
  $()('.list-view')[0].click();
  await until('list view', () => !$()('.list-container').hasClass('hidden') && $()('.grid-container').hasClass('hidden'));
  $()('.grid-view')[0].click();
  await until('grid view', () => $()('.list-container').hasClass('hidden'));
  ok('grid and list switches work');

  if (errors.length) throw new Error('page errors: ' + errors.join('; '));
  console.log('\nJoomla 3 client: ' + results.length + ' checks passed');
  window.close(); process.exit(0);
})().catch((e) => { console.error('FAILED: ' + e.message); process.exit(1); });
