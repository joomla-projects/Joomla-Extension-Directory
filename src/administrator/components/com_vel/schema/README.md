# CVE Record Format schema

Place the CVE Record Format 5.x JSON schema here as `cve-record.json`. Take
`schema/CVE_Record_Format.json` from the **`v5.2.0` tag** of https://github.com/CVEProject/cve-schema
(default branch `main`). Verified 2026-11-05 (R-02): the licence is **CC0-1.0**, the file is one
self-contained **draft-07** document of ~73 KB with no external `$ref`s, so it is copied as-is
with no bundling step.

When the file is present and `opis/json-schema` is installed in the component's vendor folder
(`composer require opis/json-schema` in `administrator/components/com_vel/`), `CveRecordValidator`
validates every generated record against it in addition to its own structural checks: the full
record against the schema root, and the CNA-container submission shape against
`#/definitions/cnaPublishedContainer`, which is a named definition in the same file. Without it,
the structural checks alone run - they cover the members the builder writes, but not the whole
schema.

`opis/json-schema` rather than `justinrainbow/json-schema` because the schema is draft-07 and
the latter is draft-04-centric.
