import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const cpes = c => (c.configurations||[]).flatMap(n=>n.nodes||[]).flatMap(n=>n.cpeMatch||[]).map(m=>m.criteria);
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';

const n2026 = v.filter(c=>c.published.startsWith('2026') && cpes(c).some(s=>s.split(':')[3]==='joomla'));
console.log('2026 cpe-vendor-joomla records:', n2026.length);
n2026.slice(0,8).forEach(c=>console.log(' ', c.id, c.sourceIdentifier, c.vulnStatus, cpes(c).filter(s=>s.split(':')[3]==='joomla')[0], '|', desc(c).slice(0,80).replace(/\s+/g,' ')));

const cna2026 = v.filter(c=>c.sourceIdentifier==='security@joomla.org' && c.published.startsWith('2026'));
console.log('\n2026 joomla-CNA records:', cna2026.length, 'with cfg:', cna2026.filter(c=>c.configurations).length);
cna2026.sort((a,b)=>b.published.localeCompare(a.published)).slice(0,10).forEach(c=>
  console.log(' ', c.id, c.published.slice(0,10), c.vulnStatus, 'cfg:'+!!c.configurations, 'v40:'+!!c.metrics?.cvssMetricV40, '|', desc(c).slice(0,100).replace(/\s+/g,' ')));
console.log('\nstatus spread 2026 CNA:', JSON.stringify(cna2026.reduce((a,c)=>{a[c.vulnStatus]=(a[c.vulnStatus]||0)+1;return a;},{})));
