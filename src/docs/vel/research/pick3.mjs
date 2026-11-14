import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const cpes = c => (c.configurations||[]).flatMap(n=>n.nodes||[]).flatMap(n=>n.cpeMatch||[]).map(m=>m.criteria);
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';

const vj = v.filter(c=>cpes(c).some(s=>s.split(':')[3]==='joomla'));
console.log('records with ANY cpe vendor "joomla":', vj.length);
const years = {}; vj.forEach(c=>{const y=c.published.slice(0,4); years[y]=(years[y]||0)+1;});
console.log('by year:', Object.entries(years).sort().map(([k,n])=>k+':'+n).join(' '));
console.log('newest:', vj.map(c=>c.published).sort().slice(-3));

// modern core: joomla CNA records describing Joomla! CMS core
const modernCore = v.filter(c=>c.sourceIdentifier==='security@joomla.org' && c.published>'2023' && /joomla!? ?cms|joomla! [0-9]/i.test(desc(c)));
console.log('\nmodern Joomla-CNA records mentioning Joomla CMS, published>2023:', modernCore.length);
modernCore.sort((a,b)=>b.published.localeCompare(a.published)).slice(0,10).forEach(c=>
  console.log(' ', c.id, c.published.slice(0,10), c.vulnStatus, 'cfg:'+!!c.configurations,
   'v40:'+!!c.metrics?.cvssMetricV40, 'v31:'+!!c.metrics?.cvssMetricV31, '|', desc(c).slice(0,90).replace(/\s+/g,' ')));

// Joomla CNA records post-2023 overall
const cna = v.filter(c=>c.sourceIdentifier==='security@joomla.org');
console.log('\nsecurity@joomla.org total:', cna.length, ' with configurations:', cna.filter(c=>c.configurations).length);
const cy={}; cna.forEach(c=>{const y=c.published.slice(0,4); cy[y]=(cy[y]||0)+1;});
console.log('by year:', Object.entries(cy).sort().map(([k,n])=>k+':'+n).join(' '));
