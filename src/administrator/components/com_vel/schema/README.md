# CVE Record Format schema

Research task R-02 places the CVE Record Format 5.x JSON schema here as `cve-record.json`
(from https://github.com/CVEProject/cve-schema, `schema/CVE_Record_Format.json`, MIT licensed).

When the file is present and `opis/json-schema` is installed in the component's vendor folder,
`CveRecordValidator` validates every generated record against it in addition to its own
structural checks. Without it, the structural checks alone run - they cover the members the
builder writes, but not the whole schema.
