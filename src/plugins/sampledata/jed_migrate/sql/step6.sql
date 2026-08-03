-- Create the staging table. JED3 keeps the listing metadata in wqyh6_jed_extensions and the editorial content
-- (title, alias, body, owner, category, state, timestamps) in wqyh6_ucm_content - this table joins the two. The
-- free_/paid_ column families the previous version of this script carried are gone: the free/paid variant merge
-- was dropped, so every JED3 extension row becomes its own listing.
CREATE TABLE combine_jed_extensions (
id INT UNSIGNED NOT NULL DEFAULT 0,
core_content_id INT UNSIGNED DEFAULT 0,
core_title VARCHAR(400),
core_alias VARCHAR(400),
core_body MEDIUMTEXT,
core_state TINYINT(1) DEFAULT 0,
core_created_user_id INT DEFAULT 0,
core_created_time DATETIME NULL DEFAULT NULL,
core_catid INT DEFAULT 0,
core_checked_out_time VARCHAR(255) DEFAULT NULL,
core_checked_out_user_id INT DEFAULT 0,
core_modified_user_id INT DEFAULT 0,
core_modified_time DATETIME NULL DEFAULT NULL,
homepage_link VARCHAR(255) DEFAULT '',
download_link VARCHAR(255) DEFAULT '',
demo_link VARCHAR(255) DEFAULT '',
support_link VARCHAR(255) DEFAULT '',
documentation_link VARCHAR(255) DEFAULT '',
license_link VARCHAR(255) DEFAULT '',
versions VARCHAR(255) DEFAULT '',
popular TINYINT(1) DEFAULT 0,
requires_registration TINYINT(1) DEFAULT 0,
type VARCHAR(8) DEFAULT '',
license VARCHAR(20) DEFAULT '',
jed_note TEXT,
update_url VARCHAR(255) DEFAULT '',
tags TEXT,
language_code VARCHAR(100) DEFAULT '',
video VARCHAR(100) DEFAULT '',
version VARCHAR(255) DEFAULT '',
uses_updater TINYINT(1) DEFAULT 0,
includes VARCHAR(100) DEFAULT '',
score DOUBLE(6, 2) DEFAULT NULL,
approved INT DEFAULT 0,
approval_label CHAR(5) DEFAULT NULL,
approved_time DATETIME NULL DEFAULT NULL,
extension_file VARCHAR(150) DEFAULT '',
second_contact_email VARCHAR(100) DEFAULT '',
functionality INT DEFAULT 0,
ease_of_use INT DEFAULT 0,
support INT DEFAULT 0,
documentation INT DEFAULT 0,
value_for_money INT DEFAULT 0,
num_reviews INT DEFAULT 0,
logo VARCHAR(255) DEFAULT '',
PRIMARY KEY (id),
KEY idx_core_content_id (core_content_id)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4;

-- Remove duplicate UCM rows in the source database. JED3 can hold more than one wqyh6_ucm_content row per
-- extension or review (an old defect around JED and VEL items), and because the staging table below is keyed on
-- the extension id, a surplus row makes the whole migration abort on a duplicate key. One row per
-- (core_type_alias, core_content_item_id) survives, chosen in this order: a published row first, then the most
-- recently modified, then the highest core_content_id as the tie breaker. Published is tested explicitly rather
-- than sorting on core_state, because the Joomla state values are not a ranking - archived is 2 and trashed is
-- -2, so a plain descending sort would keep an archived row over the live one. Only the two JED type aliases are
-- touched. Duplicates belonging to com_content and friends are left alone: core_content_item_id is only unique
-- within a type alias, so widening this would delete unrelated rows. WARNING - this is the only step in the
-- whole migration that WRITES TO THE SOURCE DATABASE. Run the migration against a copy of the JED3 database,
-- never against the live one. To see what it would remove before running it, execute the same statement with
-- "DELETE FROM wqyh6_ucm_content WHERE core_content_id IN (" replaced by "SELECT * FROM wqyh6_ucm_content WHERE
-- core_content_id IN (".
DELETE FROM wqyh6_ucm_content WHERE core_content_id IN (SELECT core_content_id FROM (SELECT core_content_id, ROW_NUMBER() OVER (PARTITION BY core_type_alias, core_content_item_id ORDER BY (core_state = 1) DESC, core_modified_time DESC, core_content_id DESC) AS rn FROM wqyh6_ucm_content WHERE core_type_alias IN ('com_jed.extension', 'com_jed.review')) ranked WHERE ranked.rn > 1);

-- Join wqyh6_jed_extensions with its UCM content. Zero dates become NULL so the copy does not fail under strict
-- SQL mode, and approved is resolved against wqyh6_jed_approval_status - in JED3 that column is a status id, not
-- a boolean.
INSERT INTO combine_jed_extensions (id, core_content_id, core_title, core_alias, core_body, core_state, core_created_user_id, core_created_time, core_catid, core_checked_out_time, core_checked_out_user_id, core_modified_user_id, core_modified_time, homepage_link, download_link, demo_link, support_link, documentation_link, license_link, versions, popular, requires_registration, type, license, jed_note, update_url, tags, language_code, video, version, uses_updater, includes, score, approved, approval_label, approved_time, extension_file, second_contact_email, functionality, ease_of_use, support, documentation, value_for_money, num_reviews) SELECT e.id, u.core_content_id, u.core_title, u.core_alias, u.core_body, u.core_state, u.core_created_user_id, NULLIF(u.core_created_time, '0000-00-00 00:00:00'), u.core_catid, u.core_checked_out_time, u.core_checked_out_user_id, u.core_modified_user_id, NULLIF(u.core_modified_time, '0000-00-00 00:00:00'), e.homepage_link, e.download_link, e.demo_link, e.support_link, e.documentation_link, e.license_link, e.versions, IFNULL(e.popular, 0), IFNULL(e.requires_registration, 0), e.type, e.license, e.jed_note, e.update_url, e.tags, e.language_code, e.video, e.version, IFNULL(e.uses_updater, 0), e.includes, e.score, IFNULL(e.approved, 0), a.label, NULLIF(e.approved_time, '0000-00-00 00:00:00'), e.extension_file, e.second_contact_email, IFNULL(e.functionality, 0), IFNULL(e.ease_of_use, 0), IFNULL(e.support, 0), IFNULL(e.documentation, 0), IFNULL(e.value_for_money, 0), IFNULL(e.num_reviews, 0) FROM wqyh6_jed_extensions e INNER JOIN wqyh6_ucm_content u ON u.core_content_item_id = e.id AND u.core_type_alias = 'com_jed.extension' LEFT JOIN wqyh6_jed_approval_status a ON a.id = e.approved ORDER BY e.id ASC;

-- Attach the logo file names from wqyh6_jed_extension_logos
UPDATE combine_jed_extensions cj INNER JOIN wqyh6_jed_extension_logos jl ON jl.extension_id = cj.id SET cj.logo = jl.file;

-- Fixing imported data - listings without a category would violate the category reference, so they are moved to
-- Miscellaneous (category 9)
UPDATE combine_jed_extensions SET core_catid = 9 WHERE core_catid = 0 OR core_catid IS NULL OR core_catid NOT IN (SELECT id FROM #__categories WHERE extension = 'com_jed');

-- Fixing imported data - empty or zero checked_out_time strings
UPDATE combine_jed_extensions SET core_checked_out_time = NULL WHERE core_checked_out_time = '' OR core_checked_out_time = '0000-00-00 00:00:00';

-- Clear the target tables
DELETE FROM #__jed_extensions;
DELETE FROM #__jed_extensions_history;
DELETE FROM #__jed_extensions_category_map;
DELETE FROM #__jed_extensions_maintainers;

-- Insert into #__jed_extensions. Mapped with a change of meaning: homepage_link -> developer_url, extension_file
-- -> internal_download_url, second_contact_email -> developer_email (the secondary contact is lost), jed_note ->
-- internal_note. includes -> extension_types was missing from the previous version of this script and is now
-- mapped, because the trophy helper renders the component/module/plugin badges from it. intro is generated from
-- the first 300 characters of the tag-stripped body: JED3 has a single body field and the new schema has intro
-- plus description, and an empty intro would leave every card in the catalogue blank. approved is derived from
-- approved_time, since the JED3 column holds a wqyh6_jed_approval_status id. The original id is kept in
-- approved_notes and its label in approved_reason so nothing is lost - confirm the label-to-boolean mapping
-- against the data before relying on it. Not migrated, because the new schema has no equivalent column:
-- license_link, tags, language_code, community_choice, download_integration_type/url, currency, price and its
-- variants, language_body, backlink, non_gpl_css_js, parent_id, related_free_paid_id, jed_checked,
-- extension_ext_libs, update_url_ok, can_update. tags is carried into the staging table so a later pass can
-- create core tag records from it.
INSERT INTO #__jed_extensions (id, name, alias, catid, owner, state, approved, approved_time, approved_notes, approved_reason, intro, description, license, requires_registration, type, extension_types, created, created_by, modified, modified_by, checked_out, checked_out_time, extension_version, entry_version, joomla_versions, download_url, support_url, demo_url, documentation_url, git_url, internal_download_url, download_key, uses_updater, update_url, developer_url, developer_email, changelog_url, score_overall, score_functionality, score_ease_of_use, score_support, score_documentation, score_value_for_money, score_count, popular, logo, overview_image, video, internal_note) SELECT id, LEFT(IFNULL(core_title, ''), 255), LEFT(IFNULL(core_alias, ''), 255), core_catid, core_created_user_id, CASE WHEN core_state = 1 THEN 1 ELSE 0 END, CASE WHEN approved = 1 THEN 1 ELSE 0 END, approved_time, CONCAT('JED3 approval_status_id=', IFNULL(approved, 0)), LEFT(IFNULL(approval_label, ''), 255), LEFT(TRIM(REGEXP_REPLACE(REGEXP_REPLACE(IFNULL(core_body, ''), '<[^>]*>', ' '), '[[:space:]]+', ' ')), 300), REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REGEXP_REPLACE(IFNULL(core_body, ''), '<[^>]*>', ''), CONCAT('&nbsp', CHAR(59)), ' '), CONCAT('&lt', CHAR(59)), '<'), CONCAT('&gt', CHAR(59)), '>'), CONCAT('&quot', CHAR(59)), '"'), CONCAT('&amp', CHAR(59)), '&'), LEFT(IFNULL(license, ''), 255), IFNULL(requires_registration, 0), CASE LOWER(TRIM(IFNULL(type, ''))) WHEN 'paid' THEN 'paid' WHEN 'freemium' THEN 'freemium' WHEN 'cloud' THEN 'cloud' ELSE 'free' END, LEFT(IFNULL(includes, ''), 255), core_created_time, core_created_user_id, core_modified_time, core_modified_user_id, NULLIF(core_checked_out_user_id, 0), core_checked_out_time, LEFT(IFNULL(version, ''), 50), 1, LEFT(IFNULL(versions, ''), 255), LEFT(IFNULL(download_link, ''), 255), LEFT(IFNULL(support_link, ''), 255), LEFT(IFNULL(demo_link, ''), 255), LEFT(IFNULL(documentation_link, ''), 255), '', LEFT(IFNULL(extension_file, ''), 255), '', IFNULL(uses_updater, 0), LEFT(IFNULL(update_url, ''), 255), LEFT(IFNULL(homepage_link, ''), 255), LEFT(IFNULL(second_contact_email, ''), 255), '', LEAST(GREATEST(ROUND(IFNULL(score, 0) / 10) / 2, 0), 5), LEAST(GREATEST(ROUND(IFNULL(functionality, 0) / 10) / 2, 0), 5), LEAST(GREATEST(ROUND(IFNULL(ease_of_use, 0) / 10) / 2, 0), 5), LEAST(GREATEST(ROUND(IFNULL(support, 0) / 10) / 2, 0), 5), LEAST(GREATEST(ROUND(IFNULL(documentation, 0) / 10) / 2, 0), 5), LEAST(GREATEST(ROUND(IFNULL(value_for_money, 0) / 10) / 2, 0), 5), 0, IFNULL(popular, 0), LEFT(IFNULL(logo, ''), 255), '', LEFT(IFNULL(video, ''), 255), jed_note FROM combine_jed_extensions;

-- Populate #__jed_extensions_category_map. JED3 allowed exactly one category per listing, so each extension gets
-- the single mapping row that matches its catid - the new multi-category queries then work against imported data
-- too.
INSERT IGNORE INTO #__jed_extensions_category_map (extension_id, catid) SELECT id, catid FROM #__jed_extensions;
