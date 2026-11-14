import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';
v.filter(c=>['audit@patchstack.com','cna@vuldb.com','cve-coordination@incibe.es','cvd@cert.pl'].includes(c.sourceIdentifier) && c.published>'2024')
 .forEach(c=>console.log(c.id, c.published.slice(0,10), c.sourceIdentifier, '|', desc(c).slice(0,130).replace(/\s+/g,' ')));
