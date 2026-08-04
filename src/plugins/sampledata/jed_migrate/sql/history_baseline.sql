-- Put the imported revisions into the same shape as the live rows, so a revision differs from
-- its listing only where the developer actually changed something. history_batch.sql copies
-- the revision body out of the JED3 JSON verbatim; step6.sql tag-stripped and entity-decoded
-- the live descriptions and then normalised them for Markdown. The same two passes are applied
-- here, once, over every revision - see step6.sql for why each rule exists.
UPDATE #__jed_extensions_history SET description = REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(IFNULL(description, ''), '<[^>]*>', ''), CONCAT('&nbsp', CHAR(59)), ' '), CONCAT('&lt', CHAR(59)), '<'), CONCAT('&gt', CHAR(59)), '>'), CONCAT('&quot', CHAR(59)), '"'), CONCAT('&amp', CHAR(59)), '&');

UPDATE #__jed_extensions_history SET description = REGEXP_REPLACE(REGEXP_REPLACE(REPLACE(REPLACE(description, '\r\n', '\n'), '\r', '\n'), '(?m)^[ \t]+', ''), '(?m)^(-+|=+)[ \t]*$', '\n\\1');

-- The intro rule from step6.sql, applied to the revisions: first paragraph, whole description
-- when that paragraph is too short to describe anything, leading block marker dropped, capped
-- at a sentence end inside 150 characters or at the last word before it.
UPDATE #__jed_extensions_history SET intro = TRIM(REGEXP_REPLACE(SUBSTRING_INDEX(description, '\n\n', 1), '[[:space:]]+', ' '));

UPDATE #__jed_extensions_history SET intro = TRIM(REGEXP_REPLACE(description, '[[:space:]]+', ' ')) WHERE CHAR_LENGTH(intro) < 30;

UPDATE #__jed_extensions_history SET intro = TRIM(REGEXP_REPLACE(intro, '^([*+>-]+|[0-9]+[.)]|#{1,6})[[:space:]]+', '')) WHERE intro REGEXP '^([*+>-]+|[0-9]+[.)]|#{1,6})[[:space:]]';

UPDATE #__jed_extensions_history SET intro = CASE WHEN CHAR_LENGTH(IFNULL(REGEXP_SUBSTR(LEFT(intro, 150), '^.*[.!?]([[:space:]]|$)'), '')) >= 60 THEN TRIM(REGEXP_SUBSTR(LEFT(intro, 150), '^.*[.!?]([[:space:]]|$)')) ELSE CONCAT(TRIM(REGEXP_REPLACE(LEFT(intro, 150), '[[:space:]]+[^[:space:]]*$', '')), '…') END WHERE CHAR_LENGTH(intro) > 150;

-- Add the current state of every listing as its one active revision, after the imported
-- history so it carries the highest id. #__jed_extensions_history is what the edit workflow
-- diffs against, so a listing without an active baseline cannot be edited cleanly after
-- go-live. This runs last so the revision carries the recomputed score_count.
INSERT INTO #__jed_extensions_history (extension_id, active, name, alias, catid, owner, state, approved, approved_time, approved_notes, approved_reason, intro, description, license, requires_registration, type, extension_types, created, created_by, modified, modified_by, checked_out, checked_out_time, extension_version, joomla_versions, download_url, support_url, demo_url, documentation_url, git_url, internal_download_url, download_key, uses_updater, update_url, developer_url, developer_email, changelog_url, score_overall, score_functionality, score_ease_of_use, score_support, score_documentation, score_value_for_money, score_count, popular, logo, overview_image, video, internal_note) SELECT id, 1, name, alias, catid, owner, state, approved, approved_time, approved_notes, approved_reason, intro, description, license, requires_registration, type, extension_types, created, created_by, modified, modified_by, checked_out, checked_out_time, extension_version, joomla_versions, download_url, support_url, demo_url, documentation_url, git_url, internal_download_url, download_key, uses_updater, update_url, developer_url, developer_email, changelog_url, score_overall, score_functionality, score_ease_of_use, score_support, score_documentation, score_value_for_money, score_count, popular, logo, overview_image, video, internal_note FROM #__jed_extensions;
