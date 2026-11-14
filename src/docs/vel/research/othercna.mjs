import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';
const recent = v.filter(c=>c.published>'2024' && c.sourceIdentifier!=='security@joomla.org' && /joomla/i.test(desc(c)));
const bySrc = {}; recent.forEach(c=>bySrc[c.sourceIdentifier]=(bySrc[c.sourceIdentifier]||0)+1);
console.log('non-Joomla-CNA Joomla records published >2024:', recent.length);
console.log(Object.entries(bySrc).sort((a,b)=>b[1]-a[1]).map(([k,n])=>`${k}(${n})`).join('\n'));
console.log('\nsamples:');
recent.sort((a,b)=>b.published.localeCompare(a.published)).slice(0,12).forEach(c=>
  console.log(' ', c.id, c.published.slice(0,10), c.sourceIdentifier, c.vulnStatus, '|', desc(c).slice(0,95).replace(/\s+/g,' ')));
