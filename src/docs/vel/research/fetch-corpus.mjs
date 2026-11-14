import { writeFileSync, existsSync } from 'node:fs';
const out = '-research/joomla-corpus.json';
if (existsSync(out)) { console.log('cached'); process.exit(0); }
const url = 'https://services.nvd.nist.gov/rest/json/cves/2.0?keywordSearch=Joomla&resultsPerPage=2000';
const r = await fetch(url);
console.log('status', r.status);
const t = await r.text();
writeFileSync(out, t);
const j = JSON.parse(t);
console.log('totalResults', j.totalResults, 'returned', j.vulnerabilities.length, 'bytes', t.length);
