import { readFileSync, writeFileSync } from 'node:fs';
const p = 'docs/vel/VEL2-PRD.md';
const L = readFileSync(p, 'utf8').split('\n');
const start = L.findIndex(l => l.startsWith('## Appendix D'));
const end = L.findIndex(l => l.startsWith('**CWE** —'));
if (start < 0 || end < 0 || end < start) { console.error('bounds fail', start, end); process.exit(1); }
const nw = readFileSync('-research/appd.md', 'utf8').split('\n');
while (nw.length && nw[nw.length - 1] === '') nw.pop();
const out = [...L.slice(0, start), ...nw, '', ...L.slice(end)];
writeFileSync(p, out.join('\n'));
console.log('Appendix D replaced: removed', end - start, 'lines, inserted', nw.length);
