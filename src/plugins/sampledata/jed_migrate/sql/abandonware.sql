-- P1-19 - import the legacy abandonware submissions.
--
-- There is no `vel_abandoned` table in the legacy database at all (P0-04 §2.2). Abandonware exists
-- only as RSForms submissions: form 9 (VEL Abandonware Form, 34 rows) and form 14 (HISTORY -
-- Abandonware, 26 rows), both staged by rsforms.sql, which is why this step runs after it.
--
-- Everything here is idempotent. The unique key on (legacy_form_id, legacy_submission_id) makes a
-- second run a no-op, which matters because the migration is designed to be retried from any step.

-- Reports first, cases second. That is the opposite of the live path, where a report joins a case,
-- and it is the right way round for an import: the case's subject is derived from the reports, so
-- the reports have to exist before there is anything to derive it from.
INSERT IGNORE INTO `#__jed_abandonware_reports`
	(`case_id`, `extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`,
	 `reason`, `reporter_user_id`, `reporter_name`, `reporter_email`, `reporter_organisation`,
	 `consent_to_process`, `consent_time`, `reporter_ip_hash`, `state`,
	 `legacy_form_id`, `legacy_submission_id`, `created`, `created_by`)
SELECT
	NULL,
	NULL,
	LEFT(COALESCE(f.Extension_name, ''), 255),
	LEFT(COALESCE(f.Last_known_version_number, ''), 100),
	LEFT(COALESCE(f.url, ''), 255),
	LEFT(COALESCE(f.Developer, ''), 255),
	f.Reason,
	-- Legacy UserId is a text column and 0 for a guest submission. 21 of form 9's 34 rows and all
	-- 26 of form 14's were filed by guests, which is precisely why the new form requires an
	-- account: the abuse protection 4.10 asks for is per-account and cannot be applied to these.
	CAST(COALESCE(NULLIF(TRIM(f.UserId), ''), '0') AS UNSIGNED),
	LEFT(COALESCE(f.NAME, ''), 255),
	LEFT(COALESCE(f.Email, ''), 255),
	'',
	-- The legacy form recorded consent as the string 'Yes'. Anything else is read as consent not
	-- given rather than as consent assumed - it is evidence, and guessing at evidence is worse
	-- than admitting there is none.
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN f.DateSubmitted ELSE NULL END,
	-- The legacy `UserIP` is deliberately dropped rather than hashed. Hashing it would carry a
	-- 2020 address forward in a form nobody can use for anything - the rate limit it exists to
	-- support looks at a 24-hour window - while still being derived from personal data.
	NULL,
	1,
	9,
	f.SubmissionId,
	COALESCE(f.DateSubmitted, NOW()),
	0
FROM old_rsform9 f
WHERE COALESCE(NULLIF(TRIM(f.Extension_name), ''), NULLIF(TRIM(f.url), ''), NULLIF(TRIM(f.Developer), '')) IS NOT NULL;

INSERT IGNORE INTO `#__jed_abandonware_reports`
	(`case_id`, `extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`,
	 `reason`, `reporter_user_id`, `reporter_name`, `reporter_email`, `reporter_organisation`,
	 `consent_to_process`, `consent_time`, `reporter_ip_hash`, `state`,
	 `legacy_form_id`, `legacy_submission_id`, `created`, `created_by`)
SELECT
	NULL,
	NULL,
	LEFT(COALESCE(f.Extension_name, ''), 255),
	LEFT(COALESCE(f.Last_known_version_number, ''), 100),
	LEFT(COALESCE(f.url, ''), 255),
	LEFT(COALESCE(f.Developer, ''), 255),
	f.Reason,
	CAST(COALESCE(NULLIF(TRIM(f.UserId), ''), '0') AS UNSIGNED),
	LEFT(COALESCE(f.NAME, ''), 255),
	LEFT(COALESCE(f.Email, ''), 255),
	'',
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN f.DateSubmitted ELSE NULL END,
	NULL,
	1,
	14,
	f.SubmissionId,
	COALESCE(f.DateSubmitted, NOW()),
	0
FROM old_rsform14 f
WHERE COALESCE(NULLIF(TRIM(f.Extension_name), ''), NULLIF(TRIM(f.url), ''), NULLIF(TRIM(f.Developer), '')) IS NOT NULL;

-- Resolve each report to a listing where the name matches one exactly and unambiguously.
--
-- Exactly, and only where there is exactly one match. A fuzzy match here would attach a stranger's
-- report to the wrong developer's listing, and the whole point of the relational link is that
-- `extension_id` means something. Where it does not resolve, the free-text tuple stands - 4.10's
-- fallback, and the reason it exists.
UPDATE `#__jed_abandonware_reports` r
JOIN (
	SELECT LOWER(TRIM(e.name)) AS lname, MIN(e.id) AS eid, COUNT(*) AS n
	FROM `#__jed_extensions` e
	WHERE e.deleted = 0
	GROUP BY LOWER(TRIM(e.name))
	HAVING COUNT(*) = 1
) m ON m.lname = LOWER(TRIM(r.extension_name))
SET r.extension_id = m.eid
WHERE r.legacy_form_id IS NOT NULL
  AND r.extension_id IS NULL
  AND TRIM(r.extension_name) <> '';

-- One case per subject, not one per report. Two people reporting the same extension in 2021 is one
-- thing that happened, and the legacy list would have shown it as two.
--
-- Grouping is by resolved listing where there is one, and by the lower-cased free-text name
-- otherwise. Imported cases open as `received` with no contact attempt recorded, which is exactly
-- right: nobody has been written to, and CaseService will refuse to mark any of them abandoned
-- until somebody is.
-- INSERT IGNORE, and the NOT EXISTS below, are both about the same thing: a listing that already
-- has an open case - because an automated signal found it first, or because a previous run of this
-- step created it - must not get a second one. The unique key would refuse it anyway; without
-- these the refusal would abort the migration step instead of skipping a row.
INSERT IGNORE INTO `#__jed_abandonware_cases`
	(`extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`,
	 `status`, `source`, `published`, `signals`, `created`, `created_by`)
SELECT
	r.extension_id,
	LEFT(MAX(r.extension_name), 255),
	LEFT(MAX(r.extension_version), 100),
	LEFT(MAX(r.extension_url), 255),
	LEFT(MAX(r.developer_name), 255),
	'received',
	'report',
	0,
	JSON_ARRAY(JSON_OBJECT(
		'source', 'report',
		'detail', CONCAT('Imported from JED3 RSForms: ', COUNT(*), ' legacy report(s), forms ',
			GROUP_CONCAT(DISTINCT r.legacy_form_id ORDER BY r.legacy_form_id SEPARATOR '/')),
		'time', DATE_FORMAT(MIN(r.created), '%Y-%m-%d %H:%i:%s')
	)),
	MIN(r.created),
	0
FROM `#__jed_abandonware_reports` r
WHERE r.legacy_form_id IS NOT NULL
  AND r.case_id IS NULL
  AND NOT EXISTS (
		SELECT 1 FROM `#__jed_abandonware_cases` x WHERE x.open_extension_id = r.extension_id
  )
GROUP BY r.extension_id, CASE WHEN r.extension_id IS NULL THEN LOWER(TRIM(r.extension_name)) ELSE NULL END;

-- Attach the reports to their case - the one just created, or the one that was already open for
-- that listing. A report joining an existing case is the correct outcome, not a collision: it is
-- the same "one case, not three tickets" rule the live path applies, arriving from the import side.
UPDATE `#__jed_abandonware_reports` r
JOIN `#__jed_abandonware_cases` c
	ON (
		(r.extension_id IS NOT NULL AND c.extension_id = r.extension_id)
		OR (r.extension_id IS NULL AND c.extension_id IS NULL
			AND LOWER(TRIM(c.extension_name)) = LOWER(TRIM(r.extension_name)))
	)
SET r.case_id = c.id
WHERE r.legacy_form_id IS NOT NULL
  AND r.case_id IS NULL;
