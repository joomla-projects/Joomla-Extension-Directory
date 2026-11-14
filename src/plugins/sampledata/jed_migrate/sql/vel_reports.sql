-- VEL2-PRD M-02 - import the legacy VEL report and developer-update submissions.
--
-- The legacy VEL intake was RSForms: forms 5 (2020 VEL Reporting Form), 10 (Old Old VEL Reporting
-- Form), 11 (History VEL Reporting Form) for vulnerability reports, and 7 (2020 VEL Update Form),
-- 12 (History Update Form) for developer updates. rsforms.sql stages each as old_rsformN, which is
-- why this step runs after it. Nothing consumed those tables until now.
--
-- The published advisories themselves are NOT here: they live in the legacy site's content
-- tables, which research task R-04 inventories, and M-01 imports them once it has.
--
-- Idempotent through the unique key on (legacy_form_id, legacy_submission_id). Every row lands as
-- `triaged` with `channel = legacy`: these reports were handled years ago and are history, not
-- today's queue. The reporter's address is kept only where consent was recorded, and the
-- address is dropped rather than hashed for the reason abandonware.sql gives.

-- Vulnerability reports: forms 5, 10, 11 share the column names that matter.
INSERT IGNORE INTO `#__vel_reports`
	(`kind`, `state`, `advisory_id`, `reporter_user_id`, `reporter_name`, `reporter_email`, `reporter_organisation`,
	 `reporter_role`, `reporter_wants_credit`, `reporter_credit_name`, `share_with_developer`,
	 `extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`, `developer_email`,
	 `vulnerability_class`, `description`, `steps_to_reproduce`, `impact`, `actively_exploited`,
	 `publicly_disclosed`, `disclosure_url`, `developer_contact_status`, `patch_url`, `fixed_version`,
	 `tracking_reference`, `additional_info`, `consent_to_process`, `consent_time`, `reporter_ip_hash`,
	 `verify_token`, `verified_time`, `channel`, `legacy_form_id`, `legacy_submission_id`, `created`, `created_by`)
SELECT
	'vulnerability',
	'triaged',
	NULL,
	CAST(COALESCE(NULLIF(TRIM(f.UserId), ''), '0') AS UNSIGNED),
	LEFT(COALESCE(f.FullName, ''), 255),
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN LEFT(COALESCE(f.Email, ''), 255) ELSE '' END,
	LEFT(COALESCE(f.Organization, ''), 255),
	'other',
	0,
	'',
	CASE WHEN LOWER(TRIM(COALESCE(f.passtodev, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	NULL,
	-- A template report names the template; an extension report the extension.
	LEFT(COALESCE(NULLIF(TRIM(f.vulExtnName), ''), NULLIF(TRIM(f.vulTemName), ''), ''), 255),
	LEFT(COALESCE(NULLIF(TRIM(f.vulExtnVer), ''), NULLIF(TRIM(f.vulTemVer), ''), ''), 100),
	LEFT(COALESCE(f.jedurl, ''), 255),
	LEFT(COALESCE(f.devname, ''), 255),
	LEFT(COALESCE(f.devEmail, ''), 255),
	-- The legacy exploit type vocabulary onto CWE ids (VEL2-PRD Appendix A).
	CASE LOWER(TRIM(COALESCE(f.exploittype, '')))
		WHEN 'sql injection' THEN 'CWE-89'
		WHEN 'xss (cross site scripting)' THEN 'CWE-79'
		WHEN 'directory traversal' THEN 'CWE-22'
		WHEN 'information disclosure' THEN 'CWE-200'
		ELSE ''
	END,
	CONCAT_WS('\n\n', NULLIF(TRIM(COALESCE(f.vuldescription, '')), ''), NULLIF(TRIM(COALESCE(f.OtherExploitDescription, '')), '')),
	COALESCE(f.vulTools, ''),
	COALESCE(f.vuiImpact, ''),
	CASE LOWER(TRIM(COALESCE(f.IsActiveExploit, ''))) WHEN 'yes' THEN 'yes' WHEN 'no' THEN 'no' ELSE 'unknown' END,
	CASE WHEN LOWER(TRIM(COALESCE(f.exploitInPublic, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	LEFT(COALESCE(f.publicExploitUrl, ''), 255),
	-- The five legacy developer-communication options, by their leading words.
	CASE
		WHEN LOWER(COALESCE(f.devradioselect, '')) LIKE 'i have not contacted%' THEN 'not_contacted'
		WHEN LOWER(COALESCE(f.devradioselect, '')) LIKE 'i have attempted%' THEN 'contacted_no_reply'
		WHEN LOWER(COALESCE(f.devradioselect, '')) LIKE 'i have already contacted%' THEN 'contacted_replied'
		WHEN LOWER(COALESCE(f.devradioselect, '')) LIKE 'i represent%' THEN 'i_am_developer'
		WHEN LOWER(COALESCE(f.devradioselect, '')) LIKE 'the developer has already%' THEN 'fixed_publicly'
		ELSE 'not_contacted'
	END,
	LEFT(COALESCE(f.updateURL, ''), 255),
	'',
	LEFT(CONCAT_WS(' ', NULLIF(TRIM(COALESCE(f.trackdb, '')), ''), NULLIF(TRIM(COALESCE(f.trackDbID, '')), '')), 255),
	CONCAT_WS('\n', NULLIF(TRIM(COALESCE(f.devAddInfo, '')), ''), NULLIF(CONCAT('Download: ', TRIM(COALESCE(f.Downloadurl, ''))), 'Download: ')),
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN f.DateSubmitted ELSE NULL END,
	NULL,
	NULL,
	f.DateSubmitted,
	'legacy',
	f.formId_int,
	f.SubmissionId,
	COALESCE(f.DateSubmitted, NOW()),
	0
FROM (
	SELECT 5 AS formId_int, SubmissionId, DateSubmitted, UserId, FullName, Email, Organization, passtodev, vulExtnName, vulExtnVer, vulTemName, vulTemVer, exploittype, OtherExploitDescription, vuldescription, vulTools, IsActiveExploit, exploitInPublic, publicExploitUrl, vuiImpact, devradioselect, updateURL, devname, devEmail, jedurl, trackdb, trackDbID, devAddInfo, Downloadurl, consentuse FROM old_rsform5
	UNION ALL
	SELECT 10, SubmissionId, DateSubmitted, UserId, FullName, Email, Organization, NULL, vulExtnName, vulExtnVer, vulTemName, vulTemVer, COALESCE(exploittype, exploit_type), OtherExploitDescription, vuldescription, vulTools, IsActiveExploit, exploitInPublic, publicExploitUrl, vuiImpact, devradioselect, updateURL, devname, devEmail, jedurl, trackdb, trackDbID, devAddInfo, Downloadurl, NULL FROM old_rsform10
	UNION ALL
	SELECT 11, SubmissionId, DateSubmitted, UserId, FullName, Email, Organization, passtodev, vulExtnName, vulExtnVer, vulTemName, vulTemVer, exploittype, OtherExploitDescription, vuldescription, vulTools, IsActiveExploit, exploitInPublic, publicExploitUrl, vuiImpact, devradioselect, updateURL, devname, devEmail, jedurl, trackdb, trackDbID, devAddInfo, Downloadurl, consentuse FROM old_rsform11
) f
WHERE COALESCE(NULLIF(TRIM(f.vulExtnName), ''), NULLIF(TRIM(f.vulTemName), ''), NULLIF(TRIM(f.vuldescription), '')) IS NOT NULL;

-- Developer updates: forms 7 and 12 become fix notices.
INSERT IGNORE INTO `#__vel_reports`
	(`kind`, `state`, `advisory_id`, `reporter_user_id`, `reporter_name`, `reporter_email`, `reporter_organisation`,
	 `reporter_role`, `reporter_wants_credit`, `reporter_credit_name`, `share_with_developer`,
	 `extension_id`, `extension_name`, `extension_version`, `extension_url`, `developer_name`, `developer_email`,
	 `vulnerability_class`, `description`, `steps_to_reproduce`, `impact`, `actively_exploited`,
	 `publicly_disclosed`, `disclosure_url`, `developer_contact_status`, `patch_url`, `fixed_version`,
	 `tracking_reference`, `additional_info`, `consent_to_process`, `consent_time`, `reporter_ip_hash`,
	 `verify_token`, `verified_time`, `channel`, `legacy_form_id`, `legacy_submission_id`, `created`, `created_by`)
SELECT
	'fix_notice',
	'triaged',
	NULL,
	CAST(COALESCE(NULLIF(TRIM(f.UserId), ''), '0') AS UNSIGNED),
	LEFT(COALESCE(f.fn, ''), 255),
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN LEFT(COALESCE(f.uMail, ''), 255) ELSE '' END,
	LEFT(COALESCE(f.updateco, ''), 255),
	'developer',
	0,
	'',
	1,
	NULL,
	LEFT(COALESCE(f.uExtension_, ''), 255),
	LEFT(COALESCE(f.oldvers, ''), 100),
	LEFT(COALESCE(f.changelog, ''), 255),
	LEFT(COALESCE(f.updateco, ''), 255),
	'',
	'',
	COALESCE(f.UDetails, ''),
	'',
	'',
	'unknown',
	0,
	'',
	'i_am_developer',
	LEFT(COALESCE(NULLIF(TRIM(f.Download_url_), ''), f.UpdateNoticeURL, ''), 255),
	LEFT(COALESCE(f.newvers, ''), 50),
	'',
	CONCAT_WS('\n', NULLIF(CONCAT('Update notice: ', TRIM(COALESCE(f.UpdateNoticeURL, ''))), 'Update notice: ')),
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN 1 ELSE 0 END,
	CASE WHEN LOWER(TRIM(COALESCE(f.consentuse, ''))) IN ('yes', '1', 'true') THEN f.DateSubmitted ELSE NULL END,
	NULL,
	NULL,
	f.DateSubmitted,
	'legacy',
	f.formId_int,
	f.SubmissionId,
	COALESCE(f.DateSubmitted, NOW()),
	0
FROM (
	SELECT 7 AS formId_int, SubmissionId, DateSubmitted, UserId, fn, updateco, uMail, uExtension_, oldvers, UDetails, newvers, UpdateNoticeURL, changelog, Download_url_, consentuse FROM old_rsform7
	UNION ALL
	SELECT 12, SubmissionId, DateSubmitted, UserId, fn, updateco, uMail, uExtension_, oldvers, COALESCE(UDetails, Extension_Update_Details), newvers, COALESCE(UpdateNoticeURL, UpdateNotice_URL), changelog, Download_url_, consentuse FROM old_rsform12
) f
WHERE COALESCE(NULLIF(TRIM(f.uExtension_), ''), NULLIF(TRIM(f.UDetails), '')) IS NOT NULL;

-- Resolve each report to a listing where the extension name matches one exactly and
-- unambiguously, the way abandonware.sql does. Anything looser would attach a stranger's listing.
UPDATE `#__vel_reports` r
INNER JOIN (
	SELECT LOWER(TRIM(`name`)) AS lname, MIN(`id`) AS id, COUNT(*) AS n
	FROM `#__jed_extensions`
	WHERE `deleted` = 0
	GROUP BY LOWER(TRIM(`name`))
	HAVING COUNT(*) = 1
) e ON e.lname = LOWER(TRIM(r.extension_name))
SET r.extension_id = e.id
WHERE r.channel = 'legacy' AND r.extension_id IS NULL AND TRIM(r.extension_name) <> '';
