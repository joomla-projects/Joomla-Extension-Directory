import { readFileSync } from 'node:fs';
const head = JSON.parse(readFileSync('tests/fixtures/vel/cve/cvelist-deltalog-head.json','utf8'));
const items = head.flatMap(e=>[...(e.new||[]),...(e.updated||[])]).slice(0,60);
const dist={}; const byAssigner={};
let n=0;
for (const it of items) {
  try {
    const j = await (await fetch(it.githubLink)).json();
    const dv = j.dataVersion; dist[dv]=(dist[dv]||0)+1; n++;
    const a=j.cveMetadata.assignerShortName; (byAssigner[dv] ??= new Set()).add(a);
  } catch(e) { /* skip */ }
}
console.log('sampled', n, 'records from the live delta');
console.log('dataVersion distribution:', JSON.stringify(dist));
for (const [k,v] of Object.entries(byAssigner)) console.log(' ', k, '->', [...v].slice(0,12).join(', '));
// also the rejected fixture shape
const rej = JSON.parse(readFileSync('tests/fixtures/vel/cve/cve-rejected.json','utf8'));
console.log('\nREJECTED record: dataVersion', rej.dataVersion, 'containers', Object.keys(rej.containers),
  'cna keys', Object.keys(rej.containers.cna), 'metadata', Object.keys(rej.cveMetadata).join(','));
console.log('rejectedReasons:', JSON.stringify(rej.containers.cna.rejectedReasons));
