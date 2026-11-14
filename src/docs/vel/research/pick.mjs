import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const cpes = c => (c.configurations||[]).flatMap(n=>n.nodes||[]).flatMap(n=>n.cpeMatch||[]).map(m=>m.criteria);
const isCore = c => cpes(c).some(s=>{const p=s.split(':'); return p[3]==='joomla' && (p[4]==='joomla\!'||p[4]==='joomla');});
const tgt = c => cpes(c).some(s=>s.split(':')[10]==='joomla');
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';

// 1. extension WITH cpe, target_sw=joomla, modern, has CVSS v3.1 + CWE
const f1 = v.filter(c=>tgt(c) && !isCore(c) && c.metrics?.cvssMetricV31 && c.weaknesses?.length);
console.log('--- f1 extension+cpe+target_sw candidates:', f1.length);
f1.slice(0,8).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), c.vulnStatus, cpes(c)[0]));

// 2. extension WITHOUT configurations, recent, description mentions joomla
const f2 = v.filter(c=>!c.configurations && /joomla/i.test(desc(c)) && c.published>'2025');
console.log('--- f2 no-config candidates:', f2.length);
f2.slice(0,8).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), c.sourceIdentifier, c.vulnStatus, !!c.metrics?.cvssMetricV40));

// 3. core
const f3 = v.filter(c=>isCore(c) && c.sourceIdentifier==='security@joomla.org' && c.published>'2023');
console.log('--- f3 core candidates:', f3.length);
f3.slice(0,8).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), cpes(c).find(s=>s.split(':')[3]==='joomla')));

// 4. unrelated keyword hit: mentions joomla nowhere in desc, or cpe vendor not joomla-ish
const f4 = v.filter(c=>!/joomla/i.test(desc(c)) && !cpes(c).some(s=>/joomla/i.test(s)));
console.log('--- f4 unrelated candidates:', f4.length);
f4.slice(0,10).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), JSON.stringify(desc(c).slice(0,110))));

// 5. rejected
const f5 = v.filter(c=>c.vulnStatus==='Rejected' || /^Rejected reason/i.test(desc(c)));
console.log('--- f5 rejected candidates:', f5.length);
f5.slice(0,8).forEach(c=>console.log(' ', c.id, c.vulnStatus, JSON.stringify(desc(c).slice(0,90))));
