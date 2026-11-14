import { writeFileSync, readFileSync } from 'node:fs';
const NVD='tests/fixtures/vel/nvd/', CVE='tests/fixtures/vel/cve/';
const corpus = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const byId = Object.fromEntries(corpus.vulnerabilities.map(x=>[x.cve.id,x]));

// --- NVD fixtures: wrap each single record in the real API envelope ---
const env = (vulns) => ({
  resultsPerPage: vulns.length, startIndex: 0, totalResults: vulns.length,
  format: 'NVD_CVE', version: '2.0', timestamp: corpus.timestamp, vulnerabilities: vulns,
});
const nvd = [
  ['nvd-extension-with-cpe.json',      'CVE-2025-2126'],   // VulDB, Analyzed, target_sw=joomla, CVSS v3.1+v4.0, CWE, CPE bounds
  ['nvd-extension-no-cpe.json',        'CVE-2026-105606'], // Joomla CNA 2026, Deferred, CVSS v4.0 Secondary only, no configurations
  ['nvd-core.json',                    'CVE-2026-73337'],  // Joomla! Core, Analyzed, cpe:2.3:a:joomla:joomla\!
  ['nvd-unrelated-keyword-hit.json',   'CVE-2024-32788'],  // "FG Joomla to WordPress" - a WordPress plugin
  ['nvd-rejected.json',                'CVE-2026-73327'],  // vulnStatus Rejected
];
for (const [f,id] of nvd) {
  if (!byId[id]) { console.log('MISSING in corpus', id); continue; }
  writeFileSync(NVD+f, JSON.stringify(env([byId[id]]), null, 2)+'\n');
  const c = byId[id].cve;
  console.log('NVD', f, id, c.vulnStatus, 'metrics:'+Object.keys(c.metrics||{}).join('/'),
    'cfg:'+!!c.configurations, 'weak:'+(c.weaknesses?.length||0), 'refs:'+(c.references?.length||0));
}

// --- CVE Program fixtures from cvelistV5 ---
const raw = id => { const [,y,s]=id.split('-');
  return `https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/${y}/${Math.floor(+s/1000)}xxx/${id}.json`; };
const cve = [
  ['cve-joomla-cna-extension.json', 'CVE-2026-105606'], // current house style + CISA-ADP
  ['cve-joomla-cna-core.json',      'CVE-2026-73337'],  // core, vendor "Joomla! Project", impacts[]
  ['cve-joomla-cna-legacy.json',    'CVE-2020-35610'],  // 2020 style: problemType type "text", no cweId, x_refsource tags
  ['cve-other-cna-extension.json',  'CVE-2024-5736'],   // CERT.PL on a Joomla extension
  ['cve-rejected.json',             'CVE-2026-73327'],  // REJECTED record shape
];
for (const [f,id] of cve) {
  const r = await fetch(raw(id));
  if (r.status !== 200) { console.log('FAIL', id, r.status); continue; }
  const t = await r.text(); writeFileSync(CVE+f, t.endsWith('\n')?t:t+'\n');
  const j = JSON.parse(t);
  console.log('CVE', f, id, j.cveMetadata.state, 'v'+j.dataVersion, 'containers:'+Object.keys(j.containers).join('/'),
    'assigner:'+j.cveMetadata.assignerShortName, t.length+'b');
}

// --- deltaLog head (shape fixture, not the 35 MB file) ---
const dl = await fetch('https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/deltaLog.json',
  { headers: { Range: 'bytes=0-24000' } });
console.log('deltaLog range status', dl.status, dl.headers.get('content-range'));
const txt = await dl.text();
// close the truncated array at the last complete top-level entry
const cut = txt.lastIndexOf('\n  },\n');
const head = txt.slice(0, cut) + '\n  }\n]\n';
JSON.parse(head); // throws if malformed
writeFileSync(CVE+'cvelist-deltalog-head.json', head);
console.log('deltaLog head entries:', JSON.parse(head).length, head.length+'b');

const d1 = await fetch('https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/delta.json');
const d1t = await d1.text(); writeFileSync(CVE+'cvelist-delta.json', d1t.endsWith('\n')?d1t:d1t+'\n');
console.log('delta.json', d1.status, d1t.length+'b');
