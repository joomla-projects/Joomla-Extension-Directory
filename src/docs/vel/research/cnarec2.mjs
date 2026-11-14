const get = async id => { const [,y,s]=id.split('-');
  return (await fetch(`https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/${y}/${Math.floor(+s/1000)}xxx/${id}.json`)).json(); };
for (const id of ['CVE-2026-105606','CVE-2026-73337']) {
  const j = await get(id); const c=j.containers.cna;
  console.log('=====', id);
  console.log('x_generator', JSON.stringify(c.x_generator));
  console.log('source', JSON.stringify(c.source));
  console.log('providerMetadata', JSON.stringify(c.providerMetadata));
  console.log('impacts', JSON.stringify(c.impacts));
  console.log('supportingMedia', JSON.stringify(c.descriptions[0].supportingMedia).slice(0,300));
  console.log('adp count', j.containers.adp.length);
  j.containers.adp.forEach(a=>console.log('  adp provider:', a.providerMetadata.shortName, 'keys:', Object.keys(a).join(','),
    'metrics:', JSON.stringify(a.metrics||[]).slice(0,220)));
}
