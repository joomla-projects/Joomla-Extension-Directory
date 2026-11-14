const raw = id => { const [,y,s]=id.split('-');
  return `https://raw.githubusercontent.com/CVEProject/cvelistV5/main/cves/${y}/${Math.floor(+s/1000)}xxx/${id}.json`; };
const ids = ['CVE-2024-5736','CVE-2024-5735','CVE-2020-35610','CVE-2023-23752','CVE-2021-23132',
             'CVE-2022-23793','CVE-2025-40636','CVE-2025-2126','CVE-2019-25753','CVE-2017-17872'];
for (const id of ids) {
  const r = await fetch(raw(id)); if (r.status!==200) { console.log(id, r.status); continue; }
  const j = await r.json();
  console.log(id.padEnd(16), 'dv='+String(j.dataVersion).padEnd(5), 'updated='+j.cveMetadata.dateUpdated,
    'assigner='+j.cveMetadata.assignerShortName);
}
