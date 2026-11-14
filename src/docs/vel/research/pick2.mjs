import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const cpes = c => (c.configurations||[]).flatMap(n=>n.nodes||[]).flatMap(n=>n.cpeMatch||[]).map(m=>m.criteria);
const isCore = c => cpes(c).some(s=>{const p=s.split(':'); return p[3]==='joomla' && (p[4]==='joomla\!'||p[4]==='joomla');});
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';

const core = v.filter(isCore);
console.log('--- core total:', core.length, ' by year:');
const byYear={}; core.forEach(c=>{const y=c.published.slice(0,4); byYear[y]=(byYear[y]||0)+1;});
console.log(Object.entries(byYear).sort().map(([k,n])=>k+':'+n).join(' '));
console.log('--- newest core:');
core.sort((a,b)=>b.published.localeCompare(a.published)).slice(0,10).forEach(c=>
  console.log(' ', c.id, c.published.slice(0,10), c.sourceIdentifier, c.vulnStatus,
    'v31:'+!!c.metrics?.cvssMetricV31, 'v40:'+!!c.metrics?.cvssMetricV40, cpes(c).find(s=>s.split(':')[3]==='joomla')));

// "unrelated": joomla mentioned in description but product is clearly not a joomla extension
console.log('\n--- records whose description mentions Joomla only as one of several platforms / as a scanner:');
const hits = v.filter(c=>{
  const d=desc(c);
  if (!/joomla/i.test(d)) return false;
  const vend = cpes(c).map(s=>s.split(':')[3]);
  if (vend.some(x=>/joomla/i.test(x))) return false;
  return /wordpress|drupal|typo3|magento|prestashop|phpbb|scanner|plesk|cpanel|installatron|softaculous/i.test(d);
});
console.log('count', hits.length);
hits.slice(0,12).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), (cpes(c)[0]||'(no cpe)'), '|', desc(c).slice(0,140).replace(/\s+/g,' ')));
