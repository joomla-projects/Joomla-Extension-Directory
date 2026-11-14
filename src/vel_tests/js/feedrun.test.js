/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Browser tests of media/com_vel/js/feedrun.js, the script that drives a feed run on the Feed
 * Runs page. jsdom loads the page as tmpl/feedruns/default.php renders it and the real script;
 * the server is scripted with the reports the real FeedRunner produced (fixtures/, written by
 * build-fixtures.php), plus failures: gateway timeouts, network loss, busy runs, expired sessions.
 *
 * Run:  cd vel_tests/js && npm install && npm test
 */
'use strict';
const path = require('path');
const fs = require('fs');
const { JSDOM } = require('jsdom');
const assert = require('assert');

const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'fixtures/feedrun-page.json'), 'utf8'));
const script = fs.readFileSync(path.join(__dirname, '../../media/com_vel/js/feedrun.js'), 'utf8');
const ini = Object.fromEntries(fs.readFileSync(path.join(__dirname, '../../administrator/components/com_vel/language/en-GB/com_vel.ini'), 'utf8')
  .split('\n').map((l) => l.match(/^([A-Z0-9_]+)="(.*)"\s*$/)).filter(Boolean).map((m) => [m[1], m[2]]));

const STEP = '/administrator/index.php?option=com_vel&task=feeditems.step&format=json';
const STOP = '/administrator/index.php?option=com_vel&task=feeditems.stop&format=json';

/** A page with the rendered template, Joomla's core API and a scripted server. */
function page({ report = fixture.report, server, csrf = 'tok123' }) {
  const dom = new JSDOM(`<!doctype html><body>${fixture.html}</body>`, { virtualConsole: new (require('jsdom').VirtualConsole)(), runScripts: 'outside-only', url: 'https://jed.test/administrator/index.php?option=com_vel&view=feedruns&run=1' });
  const w = dom.window;
  const requests = [];
  const options = { 'com_vel.feedrun': { id: fixture.runId, report, stepUrl: STEP, stopUrl: STOP, listUrl: '/list' }, 'csrf.token': csrf };

  w.Joomla = { getOptions: (k, d) => (k in options ? options[k] : d), Text: { _: (k, d) => (k in ini ? ini[k] : d) } };
  w.confirm = () => true;
  const st = w.setTimeout.bind(w);
  w.setTimeout = (fn, ms) => st(fn, Math.max(1, ms / 100));   // 100x faster
  w.fetch = async (url, init) => {
    const body = {}; for (const [k, v] of init.body.entries()) body[k] = v;
    requests.push({ url, method: init.method, headers: init.headers, body });
    const answer = await server(url, requests.length, body);
    if (answer instanceof Error) throw answer;
    return new Response(answer.body, { status: answer.status || 200, headers: { 'Content-Type': answer.type || 'application/json' } });
  };
  try { w.eval(script); } catch (e) { /* shown in the panel; asserted by the test */ }
  const $ = (id) => w.document.getElementById(`vel-feedrun-${id}`);
  return { w, $, requests, dom };
}

const json = (data, status = 200) => ({ status, body: JSON.stringify({ success: status < 400, message: null, messages: null, data }) });
const jsonError = (message, status) => ({ status, body: JSON.stringify({ success: false, message, messages: null, data: null }) });
const until = (cond, ms = 8000) => new Promise((resolve, reject) => {
  const t0 = Date.now();
  const tick = () => (cond() ? resolve() : Date.now() - t0 > ms ? reject(new Error('timeout waiting')) : setTimeout(tick, 5));
  tick();
});
const visible = (el) => !el.classList.contains('d-none');
const logText = ($) => Array.from($('log').children).map((li) => li.textContent).join('\n');

/** Replays a real sequence of reports from the PHP runner. */
const replay = (seq) => (url, n) => json(seq[Math.min(n, seq.length - 1)]);
const lastCna = () => fixture.sequences.cna[fixture.sequences.cna.length - 1];
const finalBadge = () => ({ ok: 'bg-success', partial: 'bg-warning', failed: 'bg-danger' }[lastCna().status]);
const finalAlert = () => ({ ok: 'alert-success', partial: 'alert-warning', failed: 'alert-danger' }[lastCna().status]);

const tests = {
  async 'happy path: CNA run replayed from the real runner'() {
    const seq = fixture.sequences.cna;
    let snapshotAt3 = null;
    const p = page({ server: (url, n) => { if (n === 4) snapshotAt3 = { width: p.$('bar').style.width, text: p.$('activity').textContent }; return replay(seq)(url, n); } });
    await until(() => visible(p.$('close')));
    const last = seq[seq.length - 1];
    assert.strictEqual(p.requests.length, seq.length - 1, 'one request per step');
    assert.ok(p.requests.every((r) => r.url === STEP && r.method === 'POST' && r.headers['X-CSRF-Token'] === 'tok123' && r.body.id === String(fixture.runId)), 'request shape');
    assert.strictEqual(p.$('status').textContent, last.statusLabel);
    const badge = { ok: 'bg-success', partial: 'bg-warning', failed: 'bg-danger' }[last.status];
    assert.ok(p.$('status').className.includes(badge), p.$('status').className + ' for ' + last.status);
    assert.strictEqual(p.$('seen').textContent, String(last.seen));
    assert.strictEqual(p.$('new').textContent, String(last.new));
    assert.strictEqual(p.$('bar').style.width, '100%');
    assert.ok(p.$('bar').className.includes({ ok: 'bg-success', partial: 'bg-warning', failed: 'bg-danger' }[last.status]) && !p.$('bar').className.includes('progress-bar-animated'));
    assert.ok(!visible(p.$('stop')), 'Stop hidden when done');
    const events = seq.flatMap((r) => r.events);
    assert.ok(events.length > 0);
    for (const e of events) assert.ok(logText(p.$).includes(e.id), `log shows ${e.id}`);
    const rec = seq[3];
    const pct = Math.round((rec.current / rec.total) * 100) + '%';
    assert.deepStrictEqual(snapshotAt3, { width: pct, text: rec.activity }, 'progress bar mid-run');
    const ev = new p.w.Event('beforeunload', { cancelable: true }); p.w.dispatchEvent(ev);
    assert.ok(!ev.defaultPrevented, 'no leave warning once finished');
  },

  async 'happy path: NVD run, enrichment phase shown'() {
    const seq = fixture.sequences.nvd;
    const p = page({ report: seq[0], server: replay(seq) });
    await until(() => visible(p.$('close')));
    assert.ok(logText(p.$).includes(ini.COM_VEL_FEEDRUN_PHASE_DISCOVER_START), 'discovery phase logged');
    assert.ok(logText(p.$).includes(ini.COM_VEL_FEEDRUN_PHASE_ENRICH_START), 'enrichment phase logged');
    const lines = Array.from(p.$('log').children).map((li) => li.textContent).filter((l) => / {2}(new|updated) {2}/.test(l));
    assert.ok(lines.length > 0 && lines.every((l) => l.length < 170), 'long NVD descriptions are shortened: ' + Math.max(...lines.map((l) => l.length)));
    assert.strictEqual(p.$('seen').textContent, String(seq[seq.length - 1].seen));
  },

  async 'leave warning while running'() {
    const seq = fixture.sequences.cna;
    let release; const hold = new Promise((r) => { release = r; });
    const p = page({ server: async (url, n) => { if (n === 2) await hold; return replay(seq)(url, n); } });
    await until(() => p.requests.length === 2);
    const ev = new p.w.Event('beforeunload', { cancelable: true }); p.w.dispatchEvent(ev);
    assert.ok(ev.defaultPrevented, 'warns before leaving a running page');
    release();
    await until(() => visible(p.$('close')));
  },

  async 'gateway timeout (504 HTML) is retried'() {
    const seq = fixture.sequences.cna;
    const p = page({ server: (url, n) => (n === 2 ? { status: 504, type: 'text/html', body: '<html>504 Gateway Time-out</html>' } : replay(seq)(url, n > 2 ? n - 1 : n)) });
    await until(() => visible(p.$('close')));
    assert.ok(logText(p.$).includes('The server did not answer (HTTP 504). Trying again in 2 seconds'), logText(p.$));
    assert.ok(p.$('status').className.includes(finalBadge()));
  },

  async 'a 500 JSON error is retried, not fatal'() {
    const seq = fixture.sequences.cna;
    const p = page({ server: (url, n) => (n === 2 ? jsonError('Deadlock found', 500) : replay(seq)(url, n > 2 ? n - 1 : n)) });
    await until(() => visible(p.$('close')));
    assert.ok(logText(p.$).includes('Deadlock found'));
  },

  async 'network down: gives up after 5 retries, Continue resumes'() {
    const seq = fixture.sequences.cna;
    let down = true;
    const p = page({ server: (url, n) => (down && n >= 2 ? new TypeError('Failed to fetch') : replay(seq)(url, down ? n : n - 6)) });
    await until(() => visible(p.$('continue')));
    assert.strictEqual(p.requests.length, 7, '1 ok + 6 failed attempts (first + 5 retries)');
    assert.ok(p.$('message').textContent.startsWith('The server stopped answering'), p.$('message').textContent);
    assert.ok(!visible(p.$('close')));
    down = false;
    p.$('continue').click();
    await until(() => visible(p.$('close')));
    assert.ok(p.$('status').className.includes(finalBadge()));
    assert.ok(p.$('message').className.includes(finalAlert()));
  },

  async 'busy: waits and carries on'() {
    const seq = fixture.sequences.cna;
    const p = page({ server: (url, n) => (n === 2 ? json({ ...seq[1], busy: true, events: [] }) : replay(seq)(url, n > 2 ? n - 1 : n)) });
    await until(() => p.requests.length >= 2);
    await until(() => visible(p.$('close')));
    assert.ok(p.$('status').className.includes(finalBadge()));
  },

  async 'session expired (login page, HTTP 200) is fatal and says so'() {
    const p = page({ server: () => ({ status: 200, type: 'text/html', body: '<!doctype html><title>Login</title>' }) });
    await until(() => visible(p.$('close')));
    await new Promise((r) => setTimeout(r, 100));
    assert.strictEqual(p.requests.length, 1, 'no retries');
    assert.ok(p.$('message').textContent.includes('session may have expired'), p.$('message').textContent);
  },

  async 'no permission (403 JSON) is fatal'() {
    const p = page({ server: () => jsonError('You are not permitted to use that link to directly access that page (#403).', 403) });
    await until(() => visible(p.$('close')));
    await new Promise((r) => setTimeout(r, 100));
    assert.strictEqual(p.requests.length, 1);
    assert.ok(p.$('message').textContent.includes('#403'));
  },

  async 'Stop during a step: the stop is sent after the step answers'() {
    const seq = fixture.sequences.cna;
    let release; const hold = new Promise((r) => { release = r; });
    const stopped = { ...seq[3], status: 'partial', statusLabel: 'Partial', done: true, phase: 'done', message: ini.COM_VEL_FEED_RUN_MSG_STOPPED, events: [] };
    const p = page({ server: async (url, n) => { if (url === STOP) return json(stopped); if (n === 3) await hold; return replay(seq)(url, n); } });
    await until(() => p.requests.length === 3);
    p.$('stop').click();
    assert.ok(p.$('stop').disabled);
    assert.strictEqual(p.$('activity').textContent, ini.COM_VEL_FEEDRUN_STOPPING);
    release();
    await until(() => visible(p.$('close')));
    assert.deepStrictEqual(p.requests.map((r) => r.url), [STEP, STEP, STEP, STOP]);
    assert.ok(p.$('status').className.includes('bg-warning'));
    assert.strictEqual(p.$('message').textContent, ini.COM_VEL_FEED_RUN_MSG_STOPPED);
  },

  async 'Stop between steps is sent at once; a busy stop is retried'() {
    const seq = fixture.sequences.cna;
    const stopped = { ...seq[2], status: 'partial', statusLabel: 'Partial', done: true, phase: 'done', message: 'stopped', events: [] };
    let stops = 0;
    const p = page({ server: (url, n) => (url === STOP ? (++stops === 1 ? json({ ...seq[2], busy: true, events: [] }) : json(stopped)) : replay(seq)(url, n)) });
    // >= : steps are 2 ms apart here, so an exact count can be skipped between two polls.
    await until(() => p.requests.length >= 2);
    await new Promise((r) => setTimeout(r, 1));
    const stepsAtClick = p.requests.filter((r) => r.url === STEP).length;
    p.$('stop').click();
    await until(() => visible(p.$('close')));
    assert.strictEqual(stops, 2, 'busy stop retried');
    const stepsAfter = p.requests.filter((r) => r.url === STEP).length - stepsAtClick;
    assert.ok(stepsAfter <= 1, `no new steps after Stop (at most the one in flight): ${stepsAfter}`);
    assert.strictEqual(p.requests[p.requests.length - 1].url, STOP, 'the stop is the last request');
  },

  async 'the waiting note goes when the script starts, and stays when it does not'() {
    const p = page({ server: replay(fixture.sequences.cna) });
    await until(() => p.w.document.getElementById('vel-feedrun-waiting') === null);
    const dom = new JSDOM(`<!doctype html><body>${fixture.html}</body>`);
    assert.ok(((dom.window.document.getElementById('vel-feedrun-waiting') || {}).textContent || '').includes('F12'), 'present (and says where to look) without the script');
    await until(() => visible(p.$('close')));
  },

  async 'the outbound requests of each step are listed'() {
    const seq = fixture.sequences.cna;
    const p = page({ server: replay(seq) });
    await until(() => visible(p.$('close')));
    const lines = logText(p.$);
    assert.ok(/GET cvelistV5\/cves\/deltaLog\.json \[bytes=0-\d+\] → 206 · [\d.]+ KB · \d+ ms/.test(lines), lines.slice(0, 400));
    assert.ok(/GET cvelistV5\/cves\/2026\/90xxx\/CVE-2026-\d+\.json → 200/.test(lines), 'record fetches listed');
    assert.ok(/→ 404/.test(lines) || /→ 500/.test(lines), 'failures listed too');
  },

  async 'a start-up error is shown in the panel'() {
    const p = page({ server: replay(fixture.sequences.cna), report: { ...fixture.report, elapsed: undefined, seen: undefined, done: false, status: 'running', statusLabel: null, activity: null, phase: 'read', get total() { throw new Error('boom'); } } });
    await until(() => p.$('message').textContent.includes('boom'));
    assert.ok(p.$('message').className.includes('alert-danger'));
  },

  async 'a finished run opened later: no requests'() {
    const done = fixture.sequences.cna[fixture.sequences.cna.length - 1];
    const p = page({ report: done, server: () => { throw new Error('must not fetch'); } });
    await new Promise((r) => setTimeout(r, 100));
    assert.strictEqual(p.requests.length, 0);
    assert.ok(visible(p.$('close')) && !visible(p.$('stop')));
    assert.ok(p.$('message').className.includes(finalAlert()));
  },

  async 'token falls back to the hidden form field'() {
    const seq = fixture.sequences.cna;
    const p = page({ csrf: '', server: replay(seq) });
    await until(() => visible(p.$('close')));
    assert.strictEqual(p.requests[0].body['0123456789abcdef0123456789abcdef'], '1');
  },
};

(async () => {
  let failed = 0;
  for (const [name, fn] of Object.entries(tests)) {
    try { await fn(); console.log('PASS', name); } catch (e) { failed++; console.log('FAIL', name, '\n    ', e.message); }
  }
  console.log(failed ? `${failed} FAILED` : 'ALL PASS');
  process.exit(failed ? 1 : 0);
})();
