const ids = ['CVE-2026-105606','CVE-2026-73337'];
for (const id of ids) {
  const [,y,s] = id.split('-');
  const url = `https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/${y}/${Math.floor(+s/1000)}xxx/${id}.json`;
  const r = await fetch(url); const j = await r.json();
  const c = j.containers.cna;
  console.log('=====', id, r.status, 'dataVersion', j.dataVersion, 'containers', Object.keys(j.containers));
  console.log('title:', JSON.stringify(c.title));
  console.log('desc :', JSON.stringify((c.descriptions?.[0]?.value||'').slice(0,200)));
  console.log('descKeys:', Object.keys(c.descriptions?.[0]||{}));
  console.log('affected:', JSON.stringify(c.affected, null, 1).slice(0,700));
  console.log('problemTypes:', JSON.stringify(c.problemTypes));
  console.log('metrics:', JSON.stringify(c.metrics).slice(0,400));
  console.log('cnaKeys:', Object.keys(c));
  console.log('refs:', JSON.stringify(c.references).slice(0,400));
  console.log('credits:', JSON.stringify(c.credits));
  console.log('meta:', JSON.stringify(j.cveMetadata));
}
