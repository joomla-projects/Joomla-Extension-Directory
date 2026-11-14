import { readFileSync } from 'node:fs';
const j = JSON.parse(readFileSync('-research/joomla-corpus.json','utf8'));
const v = j.vulnerabilities.map(x=>x.cve);
const desc = c => (c.descriptions.find(d=>d.lang==='en')||{}).value||'';
const cna = v.filter(c=>c.sourceIdentifier==='security@joomla.org');

const pat = {ext:0, core:0, other:0};
const others=[]; const vendors={};
for (const c of cna) {
  const d = desc(c);
  if (/^Joomla Extension\s*-\s*/i.test(d)) { pat.ext++; const m=d.match(/^Joomla Extension\s*-\s*([^-]+?)\s*-\s*/i); if(m) vendors[m[1].trim()]=(vendors[m[1].trim()]||0)+1; }
  else if (/^Joomla!? Core\s*-\s*/i.test(d)) pat.core++;
  else { pat.other++; others.push(c); }
}
console.log('security@joomla.org =', cna.length, JSON.stringify(pat));
console.log('\ndistinct vendor tokens in "Joomla Extension - X -":', Object.keys(vendors).length);
console.log(Object.entries(vendors).sort((a,b)=>b[1]-a[1]).slice(0,15).map(([k,n])=>`${k}(${n})`).join(', '));
console.log('\nnon-conforming (first 12):');
others.sort((a,b)=>b.published.localeCompare(a.published)).slice(0,12).forEach(c=>console.log(' ', c.id, c.published.slice(0,10), '|', desc(c).slice(0,100).replace(/\s+/g,' ')));
console.log('\nnon-conforming by year:', JSON.stringify(others.reduce((a,c)=>{const y=c.published.slice(0,4);a[y]=(a[y]||0)+1;return a;},{})));

// core title date-code
const core = cna.filter(c=>/^Joomla!? Core\s*-\s*/i.test(desc(c)));
console.log('\ncore records:', core.length, 'matching "[YYYYMMDD]" code:', core.filter(c=>/\[\d{8}\]/.test(desc(c))).length);
// ext title: does it always carry "< version" or range?
const ext = cna.filter(c=>/^Joomla Extension\s*-\s*/i.test(desc(c)));
console.log('ext records:', ext.length, 'v40 metrics:', ext.filter(c=>c.metrics?.cvssMetricV40).length, 'with cfg:', ext.filter(c=>c.configurations).length);
console.log('ext by year:', JSON.stringify(ext.reduce((a,c)=>{const y=c.published.slice(0,4);a[y]=(a[y]||0)+1;return a;},{})));
console.log('core by year:', JSON.stringify(core.reduce((a,c)=>{const y=c.published.slice(0,4);a[y]=(a[y]||0)+1;return a;},{})));
