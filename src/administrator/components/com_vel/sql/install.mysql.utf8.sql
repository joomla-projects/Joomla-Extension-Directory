-- VEL 2 - the Vulnerable Extension List as its own component (VEL2-PRD §6.1).
--
-- The shape of the schema is the shape of the problem: one *advisory* may cover several products
-- of one vendor, each product has its own affected version ranges, and an advisory has any number
-- of CWE classifications, references and credits. The legacy `#__jed_vel_vulnerable_item` was one
-- flat row per product, which is why it could never express the Regular Labs case in the brief
-- without five copies of the same text.
--
-- Everything structured is a child table, not a JSON blob on the advisory row. The public filters
-- query by vendor and product, and the check API queries by Joomla identity and version range;
-- a JSON column can be displayed but not indexed for either.
--
-- Personal data about reporters and correspondents lives in exactly three tables - reports,
-- participants, messages (plus the attachments they carry) - so privacy export and erasure
-- (P1-18) are queries against those and nothing else. The advisory tables never hold a name
-- that was not meant for publication.
--
-- Safe to run twice (P1-30): CREATE TABLE IF NOT EXISTS, INSERT IGNORE for every seed row, no
-- DROP. Drops live in reset.mysql.utf8.sql, which no manifest references. Schema changes after
-- go-live belong in sql/updates/mysql/<version>.sql, mirrored here so a fresh install produces the
-- same schema.
--
-- Foreign keys are scoped to com_vel's own tables. Links into #__jed_extensions, #__jed_tickets,
-- #__jed_abandonware_cases and #__users are plain indexed columns, for the reasons com_jed's
-- install file gives: a constraint would either block deleting a user who once reported something
-- or silently cascade an advisory away when a listing is removed, and neither is acceptable for
-- a security record.

SET FOREIGN_KEY_CHECKS = 0;


-- ---------------------------------------------------------------------------------------------
-- The advisory: one vulnerability, the unit of publication and of a CVE.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisories`
(
	`id`                   int unsigned  NOT NULL AUTO_INCREMENT,
	`title`                varchar(255)  NOT NULL DEFAULT '',
	`alias`                varchar(255)  NOT NULL DEFAULT '',

	-- Editorial state (brief §3.1.A): draft | ready | published | retired. See Enum\AdvisoryState
	-- for the transitions. Only `published` is ever visible outside the backend - by HTML, by
	-- JSON, by the check API, by anything.
	`state`                varchar(16)   NOT NULL DEFAULT 'draft',

	-- Public classification (brief §4.1): live | patched | abandoned. Derived by VelService from
	-- the products' fixed versions and the abandonware link unless `status_override` is set.
	`status`               varchar(16)   NOT NULL DEFAULT 'live',
	`status_override`      tinyint(1)    NOT NULL DEFAULT '0',

	-- The single advisory description (brief §3.1.C). HTML. The CVE export derives the plain
	-- text `descriptions[].value` and carries the HTML in `supportingMedia`.
	`description`          mediumtext,
	`solution`             text,
	`workaround`           text,

	-- CVSS. The vector is the source of truth; score and severity are derived and stored because
	-- the public list filters and sorts on them.
	`cvss_version`         varchar(4)    DEFAULT NULL,
	`cvss_vector`          varchar(160)  DEFAULT NULL,
	`cvss_score`           decimal(3, 1) DEFAULT NULL,
	`severity`             varchar(16)   DEFAULT NULL,
	-- When there is no CVSS (a feed record without metrics, a migrated row with only a legacy
	-- "risk level"), the severity can be set by hand with the reason recorded here.
	`severity_reason`      varchar(255)  NOT NULL DEFAULT '',

	`exploited`            varchar(8)    NOT NULL DEFAULT 'unknown',
	`discovery`            varchar(16)   NOT NULL DEFAULT 'UNKNOWN',

	-- CVE. Unique when present; NULL until reserved or learned from a feed. `cve_assigner` is the
	-- CNA short name - "Joomla" when ours, otherwise whoever published it, which the public pages
	-- say out loud.
	`cve_id`               varchar(20)   DEFAULT NULL,
	`cve_state`            varchar(16)   NOT NULL DEFAULT 'none',
	`cve_assigner`         varchar(64)   NOT NULL DEFAULT '',

	-- The three dates the brief says editors must never type (§3.1.B), plus the ones the workflow
	-- needs around them. VelService is the only writer of the first three.
	`date_reserved`        datetime      DEFAULT NULL,
	`date_published`       datetime      DEFAULT NULL,
	`date_updated`         datetime      DEFAULT NULL,
	`date_public`          datetime      DEFAULT NULL,
	`date_reported`        datetime      DEFAULT NULL,
	`date_vendor_notified` datetime      DEFAULT NULL,
	`date_fixed`           datetime      DEFAULT NULL,

	-- Retirement: the advisory stays reachable at its URL with a notice, leaves the lists and the
	-- RSS, and sits in the dataset as withdrawn for long enough that every checker drops it.
	`retired_reason`       varchar(32)   DEFAULT NULL,
	`date_retired`         datetime      DEFAULT NULL,

	-- Bridges. At most one JED ticket per advisory (VEL2-PRD §6.9.2), at most one abandonware
	-- case (§6.9.4). Both nullable, neither constrained - they point across components.
	`external_ticket_url`  varchar(255)  NOT NULL DEFAULT '',
	`jed_ticket_id`        int unsigned  DEFAULT NULL,
	`abandonware_case_id`  int unsigned  DEFAULT NULL,

	-- manual | report | cna | nvd | legacy. What created the row; never changes.
	`source`               varchar(16)   NOT NULL DEFAULT 'manual',

	`assigned_to`          int unsigned  DEFAULT NULL,

	-- Never public.
	`internal_notes`       mediumtext,

	-- The last CVE JSON the builder produced or CVE Services accepted, for diffing.
	`published_json`       mediumtext,

	`created`              datetime      NOT NULL,
	`created_by`           int unsigned  NOT NULL DEFAULT '0',
	`modified`             datetime      DEFAULT NULL,
	`modified_by`          int unsigned  DEFAULT NULL,
	`checked_out`          int unsigned  DEFAULT NULL,
	`checked_out_time`     datetime      DEFAULT NULL,
	`hits`                 int unsigned  NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_advisories_cve` (`cve_id`),
	UNIQUE KEY `UQ_vel_advisories_alias` (`alias`),
	KEY `IDX_vel_advisories_public` (`state`, `status`, `date_published`),
	KEY `IDX_vel_advisories_updated` (`state`, `date_updated`),
	KEY `IDX_vel_advisories_severity` (`severity`),
	KEY `IDX_vel_advisories_score` (`cvss_score`),
	KEY `IDX_vel_advisories_assigned` (`assigned_to`),
	KEY `IDX_vel_advisories_abandonware` (`abandonware_case_id`),
	KEY `IDX_vel_advisories_ticket` (`jed_ticket_id`),
	KEY `IDX_vel_advisories_public_date` (`date_public`),
	FULLTEXT KEY `FT_vel_advisories_text` (`title`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Affected products (brief §3.1.E/F, §5.3). Several per advisory, one vendor.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisory_products`
(
	`id`             int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id`    int unsigned NOT NULL,

	`vendor`         varchar(255) NOT NULL DEFAULT '',
	`product`        varchar(255) NOT NULL DEFAULT '',

	-- The JED listing, when there is one. Snapshot semantics as com_abandonware: the free-text
	-- columns stay as entered, because a listing can be renamed or removed under an advisory and
	-- the advisory still has to say what it was about.
	`extension_id`   int unsigned DEFAULT NULL,
	`supplier_url`   varchar(255) NOT NULL DEFAULT '',

	-- The Joomla identity (§5.3): what an installed site can be matched on. NULL means "not known
	-- yet", which the check API reports as such rather than as "not affected".
	`ext_type`       varchar(16)  DEFAULT NULL,
	`element`        varchar(100) DEFAULT NULL,
	`folder`         varchar(50)  DEFAULT NULL,
	`client_id`      tinyint      DEFAULT NULL,

	`fixed_version`  varchar(50)  NOT NULL DEFAULT '',
	`patch_url`      varchar(255) NOT NULL DEFAULT '',

	-- CVE `defaultStatus`: unaffected | affected | unknown.
	`default_status` varchar(16)  NOT NULL DEFAULT 'unaffected',
	`ordering`       int          NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_products_advisory` (`advisory_id`),
	KEY `IDX_vel_products_extension` (`extension_id`),
	KEY `IDX_vel_products_identity` (`ext_type`, `element`, `folder`, `client_id`),
	KEY `IDX_vel_products_vendor` (`vendor`),
	CONSTRAINT `FK_vel_products_advisory` FOREIGN KEY (`advisory_id`) REFERENCES `#__vel_advisories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- Version ranges per product. Only affected ranges are stored (brief §3.1.E); both bounds are
-- inclusive, and `*` on either side means unbounded.
CREATE TABLE IF NOT EXISTS `#__vel_advisory_versions`
(
	`id`           int unsigned NOT NULL AUTO_INCREMENT,
	`product_id`   int unsigned NOT NULL,
	`version_from` varchar(50)  NOT NULL DEFAULT '*',
	`version_to`   varchar(50)  NOT NULL DEFAULT '*',
	-- CVE `versionType`: custom | semver. Joomla versions are not reliably semver, so `custom`.
	`version_type` varchar(16)  NOT NULL DEFAULT 'custom',
	`ordering`     int          NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_versions_product` (`product_id`),
	CONSTRAINT `FK_vel_versions_product` FOREIGN KEY (`product_id`) REFERENCES `#__vel_advisory_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Problem classification (brief §3.1.D): the 1-n list of CWE id / description pairs.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisory_cwes`
(
	`id`          int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id` int unsigned NOT NULL,
	`cwe_id`      varchar(16)  NOT NULL,
	-- Copied from #__vel_cwe at selection time and then editable: the CVE record carries the
	-- text, and MITRE renames entries between releases.
	`description` varchar(255) NOT NULL DEFAULT '',
	`ordering`    int          NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_cwes_advisory` (`advisory_id`),
	KEY `IDX_vel_cwes_cwe` (`cwe_id`),
	CONSTRAINT `FK_vel_cwes_advisory` FOREIGN KEY (`advisory_id`) REFERENCES `#__vel_advisories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- The CWE reference list, for the autocomplete. Seeded below with the entries that cover what a
-- Joomla extension advisory realistically names; refreshable from MITRE's XML (R-03).
CREATE TABLE IF NOT EXISTS `#__vel_cwe`
(
	`cwe_id` varchar(16)  NOT NULL,
	`name`   varchar(255) NOT NULL,
	`status` varchar(16)  NOT NULL DEFAULT 'stable',
	PRIMARY KEY (`cwe_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- References (brief §3.1.G). `tags` is a JSON array of CVE 5.x reference tags.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisory_references`
(
	`id`          int unsigned  NOT NULL AUTO_INCREMENT,
	`advisory_id` int unsigned  NOT NULL,
	`url`         varchar(1024) NOT NULL,
	`name`        varchar(255)  NOT NULL DEFAULT '',
	`tags`        varchar(512)  NOT NULL DEFAULT '[]',
	`ordering`    int           NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_references_advisory` (`advisory_id`),
	CONSTRAINT `FK_vel_references_advisory` FOREIGN KEY (`advisory_id`) REFERENCES `#__vel_advisories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Credits (brief §3.1.H). `is_public = 0` keeps a credit internal - an anonymous reporter is
-- still recorded, just never exported or shown.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisory_credits`
(
	`id`          int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id` int unsigned NOT NULL,
	`name`        varchar(255) NOT NULL DEFAULT '',
	-- CVE 5.x credit type: finder | reporter | analyst | coordinator | remediation_developer |
	-- remediation_reviewer | remediation_verifier | tool | sponsor | other.
	`role`        varchar(32)  NOT NULL DEFAULT 'finder',
	`user_id`     int unsigned DEFAULT NULL,
	`report_id`   int unsigned DEFAULT NULL,
	`is_public`   tinyint(1)   NOT NULL DEFAULT '1',
	`ordering`    int          NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_credits_advisory` (`advisory_id`),
	KEY `IDX_vel_credits_report` (`report_id`),
	CONSTRAINT `FK_vel_credits_advisory` FOREIGN KEY (`advisory_id`) REFERENCES `#__vel_advisories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Published revisions: a JSON snapshot every time an advisory is published or a published one is
-- saved. "What did we say on date X" is a question a security record has to be able to answer.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_advisory_history`
(
	`id`          int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id` int unsigned NOT NULL,
	`revision`    int unsigned NOT NULL DEFAULT '1',
	`state`       varchar(16)  NOT NULL DEFAULT '',
	`snapshot`    mediumtext   NOT NULL,
	`created`     datetime     NOT NULL,
	`created_by`  int unsigned NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_history_revision` (`advisory_id`, `revision`),
	CONSTRAINT `FK_vel_history_advisory` FOREIGN KEY (`advisory_id`) REFERENCES `#__vel_advisories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Reports: one submission, one person, one moment (VEL2-PRD §6.1.7). Never public.
--
-- Modelled on #__jed_abandonware_reports: a report is an event, an advisory is a process, and
-- a report joins an advisory only once somebody on the team says it does.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_reports`
(
	`id`                       int unsigned  NOT NULL AUTO_INCREMENT,

	-- vulnerability | fix_notice. One table, two forms.
	`kind`                     varchar(16)   NOT NULL DEFAULT 'vulnerability',
	-- new | verifying | triaged | accepted | duplicate | rejected | spam.
	`state`                    varchar(16)   NOT NULL DEFAULT 'new',
	`advisory_id`              int unsigned  DEFAULT NULL,

	-- The reporter. `reporter_user_id` is 0 for a guest (D-3).
	`reporter_user_id`         int unsigned  NOT NULL DEFAULT '0',
	`reporter_name`            varchar(255)  NOT NULL DEFAULT '',
	`reporter_email`           varchar(255)  NOT NULL DEFAULT '',
	`reporter_organisation`    varchar(255)  NOT NULL DEFAULT '',
	`reporter_role`            varchar(16)   NOT NULL DEFAULT 'other',
	`reporter_wants_credit`    tinyint(1)    NOT NULL DEFAULT '1',
	`reporter_credit_name`     varchar(255)  NOT NULL DEFAULT '',
	`share_with_developer`     tinyint(1)    NOT NULL DEFAULT '1',

	-- What the reporter said the extension was. Kept verbatim even once the advisory resolves it
	-- to a listing, because the two can disagree and the disagreement is worth seeing.
	`extension_id`             int unsigned  DEFAULT NULL,
	`extension_name`           varchar(255)  NOT NULL DEFAULT '',
	`extension_version`        varchar(100)  NOT NULL DEFAULT '',
	`extension_url`            varchar(255)  NOT NULL DEFAULT '',
	`developer_name`           varchar(255)  NOT NULL DEFAULT '',
	`developer_email`          varchar(255)  NOT NULL DEFAULT '',

	`vulnerability_class`      varchar(16)   NOT NULL DEFAULT '',
	`description`              mediumtext,
	`steps_to_reproduce`       mediumtext,
	`impact`                   text,
	`actively_exploited`       varchar(8)    NOT NULL DEFAULT 'unknown',
	`publicly_disclosed`       tinyint(1)    NOT NULL DEFAULT '0',
	`disclosure_url`           varchar(255)  NOT NULL DEFAULT '',
	`developer_contact_status` varchar(32)   NOT NULL DEFAULT 'not_contacted',
	`patch_url`                varchar(255)  NOT NULL DEFAULT '',
	`fixed_version`            varchar(50)   NOT NULL DEFAULT '',
	`tracking_reference`       varchar(255)  NOT NULL DEFAULT '',
	`additional_info`          text,

	-- Consent is evidence, and evidence with no date is not much (com_abandonware's reasoning).
	`consent_to_process`       tinyint(1)    NOT NULL DEFAULT '0',
	`consent_time`             datetime      DEFAULT NULL,

	-- A hash, never the address (P1-12's reasoning): still deduplicates, still rate-limits, is
	-- not personal data that P1-18 then has to export and erase.
	`reporter_ip_hash`         varbinary(32) DEFAULT NULL,

	-- D-3: a guest report waits in `verifying` until the link in the confirmation mail is opened.
	-- The token is stored hashed.
	`verify_token`             varchar(64)   DEFAULT NULL,
	`verified_time`            datetime      DEFAULT NULL,

	-- web | email | legacy.
	`channel`                  varchar(16)   NOT NULL DEFAULT 'web',

	-- Idempotency key for the RSForms import (forms 5/10/11 reports, 7/12 developer updates).
	`legacy_form_id`           int unsigned  DEFAULT NULL,
	`legacy_submission_id`     int unsigned  DEFAULT NULL,

	`created`                  datetime      NOT NULL,
	`created_by`               int unsigned  NOT NULL DEFAULT '0',
	`modified`                 datetime      DEFAULT NULL,
	`modified_by`              int unsigned  DEFAULT NULL,
	`checked_out`              int unsigned  DEFAULT NULL,
	`checked_out_time`         datetime      DEFAULT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_reports_legacy` (`legacy_form_id`, `legacy_submission_id`),
	KEY `IDX_vel_reports_state` (`state`, `kind`),
	KEY `IDX_vel_reports_advisory` (`advisory_id`),
	KEY `IDX_vel_reports_reporter` (`reporter_user_id`),
	KEY `IDX_vel_reports_email` (`reporter_email`),
	KEY `IDX_vel_reports_ip` (`reporter_ip_hash`, `created`),
	KEY `IDX_vel_reports_extension` (`extension_id`),
	KEY `IDX_vel_reports_verify` (`verify_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Case communication (VEL2-PRD §6.1.8, D-2 option B).
--
-- A participant is a person in a case: the reporter, a vendor contact, or a team member. A guest
-- reaches the case through `access_token` (stored hashed) rather than an account, which is what
-- makes "reporters must not need a JED account" (D-3) hold for the whole conversation and not
-- only for the first form.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_participants`
(
	`id`            int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id`   int unsigned DEFAULT NULL,
	`report_id`     int unsigned DEFAULT NULL,
	-- reporter | vendor | team | other.
	`role`          varchar(16)  NOT NULL DEFAULT 'other',
	`name`          varchar(255) NOT NULL DEFAULT '',
	`email`         varchar(255) NOT NULL DEFAULT '',
	`user_id`       int unsigned DEFAULT NULL,
	`access_token`  varchar(64)  DEFAULT NULL,
	`token_expires` datetime     DEFAULT NULL,
	`notify`        tinyint(1)   NOT NULL DEFAULT '1',
	`created`       datetime     NOT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_participants_token` (`access_token`),
	KEY `IDX_vel_participants_advisory` (`advisory_id`),
	KEY `IDX_vel_participants_report` (`report_id`),
	KEY `IDX_vel_participants_email` (`email`),
	KEY `IDX_vel_participants_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__vel_messages`
(
	`id`             int unsigned NOT NULL AUTO_INCREMENT,
	`advisory_id`    int unsigned DEFAULT NULL,
	`report_id`      int unsigned DEFAULT NULL,
	-- NULL means the team wrote it.
	`participant_id` int unsigned DEFAULT NULL,
	-- in | out | internal.
	`direction`      varchar(8)   NOT NULL DEFAULT 'out',
	-- web | email | system.
	`channel`        varchar(8)   NOT NULL DEFAULT 'web',
	`subject`        varchar(255) NOT NULL DEFAULT '',
	`body`           mediumtext,
	-- RFC 5322 ids, so a reply threads by In-Reply-To first and by the subject token second.
	`message_id`     varchar(255) DEFAULT NULL,
	`in_reply_to`    varchar(255) DEFAULT NULL,
	-- team | reporter | vendor | all. Who may read it on the case page.
	`visibility`     varchar(8)   NOT NULL DEFAULT 'team',
	`created`        datetime     NOT NULL,
	`created_by`     int unsigned NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_messages_advisory` (`advisory_id`, `created`),
	KEY `IDX_vel_messages_report` (`report_id`, `created`),
	KEY `IDX_vel_messages_participant` (`participant_id`),
	KEY `IDX_vel_messages_message_id` (`message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- Stored outside the web root and served only through a controller that checks `vel.communicate`
-- or a matching participant token. A proof of concept is never public.
CREATE TABLE IF NOT EXISTS `#__vel_attachments`
(
	`id`          int unsigned NOT NULL AUTO_INCREMENT,
	`message_id`  int unsigned DEFAULT NULL,
	`report_id`   int unsigned DEFAULT NULL,
	`filename`    varchar(255) NOT NULL,
	`stored_name` varchar(255) NOT NULL,
	`mime`        varchar(128) NOT NULL DEFAULT 'application/octet-stream',
	`size`        int unsigned NOT NULL DEFAULT '0',
	`sha256`      char(64)     NOT NULL DEFAULT '',
	`created`     datetime     NOT NULL,
	`created_by`  int unsigned NOT NULL DEFAULT '0',

	PRIMARY KEY (`id`),
	KEY `IDX_vel_attachments_message` (`message_id`),
	KEY `IDX_vel_attachments_report` (`report_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Inbound feeds (VEL2-PRD §6.6). A run is one pass of one source; an item is one external record
-- as last seen, with the team's decision about it. Nothing in here publishes anything.
-- ---------------------------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `#__vel_feed_runs`
(
	`id`            int unsigned NOT NULL AUTO_INCREMENT,
	-- cna | nvd.
	`source`        varchar(8)   NOT NULL,
	`started`       datetime     NOT NULL,
	`finished`      datetime     DEFAULT NULL,
	`window_from`   datetime     DEFAULT NULL,
	`window_to`     datetime     DEFAULT NULL,
	`items_seen`    int unsigned NOT NULL DEFAULT '0',
	`items_new`     int unsigned NOT NULL DEFAULT '0',
	`items_updated` int unsigned NOT NULL DEFAULT '0',
	-- running | ok | partial | failed.
	`status`        varchar(8)   NOT NULL DEFAULT 'running',
	`message`       text,

	PRIMARY KEY (`id`),
	KEY `IDX_vel_feed_runs_source` (`source`, `started`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__vel_feed_items`
(
	`id`               int unsigned NOT NULL AUTO_INCREMENT,
	`source`           varchar(8)   NOT NULL,
	`external_id`      varchar(32)  NOT NULL,
	`assigner`         varchar(64)  NOT NULL DEFAULT '',
	`title`            varchar(255) NOT NULL DEFAULT '',
	`fetched`          datetime     NOT NULL,
	`external_updated` datetime     DEFAULT NULL,
	`content_hash`     char(64)     NOT NULL DEFAULT '',
	`payload`          mediumtext,
	-- joomla_cna | joomla_extension_other_cna | joomla_core | unrelated | unknown.
	`classification`   varchar(32)  NOT NULL DEFAULT 'unknown',
	-- pending | created | merged | ignored.
	`decision`         varchar(16)  NOT NULL DEFAULT 'pending',
	`advisory_id`      int unsigned DEFAULT NULL,
	`decided_by`       int unsigned DEFAULT NULL,
	`decided_time`     datetime     DEFAULT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_feed_items_external` (`source`, `external_id`),
	KEY `IDX_vel_feed_items_decision` (`decision`, `classification`),
	KEY `IDX_vel_feed_items_advisory` (`advisory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------------------------
-- Mail templates. Seeded here rather than by script.php so that a fresh install has them before
-- the first report arrives; script.php keeps them in step on update.
-- ---------------------------------------------------------------------------------------------
INSERT IGNORE INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_vel.report_verify', 'com_vel', '', 'COM_VEL_MAIL_REPORT_VERIFY_SUBJECT', 'COM_VEL_MAIL_REPORT_VERIFY_BODY', '', '', '{"tags":["sitename","reference","verifylink","extensionname"]}'),
('com_vel.report_received', 'com_vel', '', 'COM_VEL_MAIL_REPORT_RECEIVED_SUBJECT', 'COM_VEL_MAIL_REPORT_RECEIVED_BODY', '', '', '{"tags":["sitename","reference","caselink","extensionname"]}'),
('com_vel.message', 'com_vel', '', 'COM_VEL_MAIL_MESSAGE_SUBJECT', 'COM_VEL_MAIL_MESSAGE_BODY', '', '', '{"tags":["sitename","reference","caselink","message","subject"]}'),
('com_vel.vendor_notification', 'com_vel', '', 'COM_VEL_MAIL_VENDOR_NOTIFICATION_SUBJECT', 'COM_VEL_MAIL_VENDOR_NOTIFICATION_BODY', '', '', '{"tags":["sitename","reference","caselink","extensionname","summary","disclosuredate","responsedays"]}'),
('com_vel.advisory_published', 'com_vel', '', 'COM_VEL_MAIL_ADVISORY_PUBLISHED_SUBJECT', 'COM_VEL_MAIL_ADVISORY_PUBLISHED_BODY', '', '', '{"tags":["sitename","reference","title","advisorylink","cveid"]}'),
('com_vel.fix_received', 'com_vel', '', 'COM_VEL_MAIL_FIX_RECEIVED_SUBJECT', 'COM_VEL_MAIL_FIX_RECEIVED_BODY', '', '', '{"tags":["sitename","reference","extensionname","fixedversion"]}');


-- ---------------------------------------------------------------------------------------------
-- CWE reference list. The practical subset for web extensions: the CWE Top 25 plus the entries
-- that recur in Joomla advisories. R-03 loads the full MITRE list; this is what the autocomplete
-- offers on day one.
-- ---------------------------------------------------------------------------------------------
INSERT IGNORE INTO `#__vel_cwe` (`cwe_id`, `name`) VALUES
('CWE-20', 'Improper Input Validation'),
('CWE-22', 'Improper Limitation of a Pathname to a Restricted Directory (''Path Traversal'')'),
('CWE-23', 'Relative Path Traversal'),
('CWE-73', 'External Control of File Name or Path'),
('CWE-74', 'Improper Neutralization of Special Elements in Output Used by a Downstream Component (''Injection'')'),
('CWE-77', 'Improper Neutralization of Special Elements used in a Command (''Command Injection'')'),
('CWE-78', 'Improper Neutralization of Special Elements used in an OS Command (''OS Command Injection'')'),
('CWE-79', 'Improper Neutralization of Input During Web Page Generation (''Cross-site Scripting'')'),
('CWE-80', 'Improper Neutralization of Script-Related HTML Tags in a Web Page (Basic XSS)'),
('CWE-88', 'Improper Neutralization of Argument Delimiters in a Command (''Argument Injection'')'),
('CWE-89', 'Improper Neutralization of Special Elements used in an SQL Command (''SQL Injection'')'),
('CWE-90', 'Improper Neutralization of Special Elements used in an LDAP Query (''LDAP Injection'')'),
('CWE-91', 'XML Injection (aka Blind XPath Injection)'),
('CWE-93', 'Improper Neutralization of CRLF Sequences (''CRLF Injection'')'),
('CWE-94', 'Improper Control of Generation of Code (''Code Injection'')'),
('CWE-95', 'Improper Neutralization of Directives in Dynamically Evaluated Code (''Eval Injection'')'),
('CWE-98', 'Improper Control of Filename for Include/Require Statement in PHP Program (''PHP Remote File Inclusion'')'),
('CWE-113', 'Improper Neutralization of CRLF Sequences in HTTP Headers (''HTTP Request/Response Splitting'')'),
('CWE-116', 'Improper Encoding or Escaping of Output'),
('CWE-119', 'Improper Restriction of Operations within the Bounds of a Memory Buffer'),
('CWE-120', 'Buffer Copy without Checking Size of Input (''Classic Buffer Overflow'')'),
('CWE-125', 'Out-of-bounds Read'),
('CWE-190', 'Integer Overflow or Wraparound'),
('CWE-200', 'Exposure of Sensitive Information to an Unauthorized Actor'),
('CWE-201', 'Insertion of Sensitive Information Into Sent Data'),
('CWE-203', 'Observable Discrepancy'),
('CWE-209', 'Generation of Error Message Containing Sensitive Information'),
('CWE-212', 'Improper Removal of Sensitive Information Before Storage or Transfer'),
('CWE-219', 'Storage of File with Sensitive Data Under Web Root'),
('CWE-250', 'Execution with Unnecessary Privileges'),
('CWE-256', 'Plaintext Storage of a Password'),
('CWE-259', 'Use of Hard-coded Password'),
('CWE-266', 'Incorrect Privilege Assignment'),
('CWE-269', 'Improper Privilege Management'),
('CWE-276', 'Incorrect Default Permissions'),
('CWE-284', 'Improper Access Control'),
('CWE-285', 'Improper Authorization'),
('CWE-287', 'Improper Authentication'),
('CWE-288', 'Authentication Bypass Using an Alternate Path or Channel'),
('CWE-290', 'Authentication Bypass by Spoofing'),
('CWE-294', 'Authentication Bypass by Capture-replay'),
('CWE-295', 'Improper Certificate Validation'),
('CWE-305', 'Authentication Bypass by Primary Weakness'),
('CWE-306', 'Missing Authentication for Critical Function'),
('CWE-307', 'Improper Restriction of Excessive Authentication Attempts'),
('CWE-311', 'Missing Encryption of Sensitive Data'),
('CWE-312', 'Cleartext Storage of Sensitive Information'),
('CWE-319', 'Cleartext Transmission of Sensitive Information'),
('CWE-326', 'Inadequate Encryption Strength'),
('CWE-327', 'Use of a Broken or Risky Cryptographic Algorithm'),
('CWE-328', 'Use of Weak Hash'),
('CWE-330', 'Use of Insufficiently Random Values'),
('CWE-338', 'Use of Cryptographically Weak Pseudo-Random Number Generator (PRNG)'),
('CWE-345', 'Insufficient Verification of Data Authenticity'),
('CWE-346', 'Origin Validation Error'),
('CWE-352', 'Cross-Site Request Forgery (CSRF)'),
('CWE-362', 'Concurrent Execution using Shared Resource with Improper Synchronization (''Race Condition'')'),
('CWE-367', 'Time-of-check Time-of-use (TOCTOU) Race Condition'),
('CWE-384', 'Session Fixation'),
('CWE-400', 'Uncontrolled Resource Consumption'),
('CWE-404', 'Improper Resource Shutdown or Release'),
('CWE-416', 'Use After Free'),
('CWE-425', 'Direct Request (''Forced Browsing'')'),
('CWE-426', 'Untrusted Search Path'),
('CWE-427', 'Uncontrolled Search Path Element'),
('CWE-434', 'Unrestricted Upload of File with Dangerous Type'),
('CWE-436', 'Interpretation Conflict'),
('CWE-444', 'Inconsistent Interpretation of HTTP Requests (''HTTP Request/Response Smuggling'')'),
('CWE-451', 'User Interface (UI) Misrepresentation of Critical Information'),
('CWE-470', 'Use of Externally-Controlled Input to Select Classes or Code (''Unsafe Reflection'')'),
('CWE-476', 'NULL Pointer Dereference'),
('CWE-477', 'Use of Obsolete Function'),
('CWE-494', 'Download of Code Without Integrity Check'),
('CWE-502', 'Deserialization of Untrusted Data'),
('CWE-521', 'Weak Password Requirements'),
('CWE-522', 'Insufficiently Protected Credentials'),
('CWE-525', 'Use of Web Browser Cache Containing Sensitive Information'),
('CWE-532', 'Insertion of Sensitive Information into Log File'),
('CWE-538', 'Insertion of Sensitive Information into Externally-Accessible File or Directory'),
('CWE-539', 'Use of Persistent Cookies Containing Sensitive Information'),
('CWE-548', 'Exposure of Information Through Directory Listing'),
('CWE-552', 'Files or Directories Accessible to External Parties'),
('CWE-565', 'Reliance on Cookies without Validation and Integrity Checking'),
('CWE-601', 'URL Redirection to Untrusted Site (''Open Redirect'')'),
('CWE-610', 'Externally Controlled Reference to a Resource in Another Sphere'),
('CWE-611', 'Improper Restriction of XML External Entity Reference'),
('CWE-613', 'Insufficient Session Expiration'),
('CWE-614', 'Sensitive Cookie in HTTPS Session Without ''Secure'' Attribute'),
('CWE-620', 'Unverified Password Change'),
('CWE-639', 'Authorization Bypass Through User-Controlled Key'),
('CWE-640', 'Weak Password Recovery Mechanism for Forgotten Password'),
('CWE-641', 'Improper Restriction of Names for Files and Other Resources'),
('CWE-642', 'External Control of Critical State Data'),
('CWE-643', 'Improper Neutralization of Data within XPath Expressions (''XPath Injection'')'),
('CWE-644', 'Improper Neutralization of HTTP Headers for Scripting Syntax'),
('CWE-646', 'Reliance on File Name or Extension of Externally-Supplied File'),
('CWE-648', 'Incorrect Use of Privileged APIs'),
('CWE-650', 'Trusting HTTP Permission Methods on the Server Side'),
('CWE-668', 'Exposure of Resource to Wrong Sphere'),
('CWE-669', 'Incorrect Resource Transfer Between Spheres'),
('CWE-670', 'Always-Incorrect Control Flow Implementation'),
('CWE-672', 'Operation on a Resource after Expiration or Release'),
('CWE-674', 'Uncontrolled Recursion'),
('CWE-676', 'Use of Potentially Dangerous Function'),
('CWE-681', 'Incorrect Conversion between Numeric Types'),
('CWE-682', 'Incorrect Calculation'),
('CWE-693', 'Protection Mechanism Failure'),
('CWE-697', 'Incorrect Comparison'),
('CWE-703', 'Improper Check or Handling of Exceptional Conditions'),
('CWE-706', 'Use of Incorrectly-Resolved Name or Reference'),
('CWE-707', 'Improper Neutralization'),
('CWE-732', 'Incorrect Permission Assignment for Critical Resource'),
('CWE-749', 'Exposed Dangerous Method or Function'),
('CWE-754', 'Improper Check for Unusual or Exceptional Conditions'),
('CWE-755', 'Improper Handling of Exceptional Conditions'),
('CWE-759', 'Use of a One-Way Hash without a Salt'),
('CWE-770', 'Allocation of Resources Without Limits or Throttling'),
('CWE-772', 'Missing Release of Resource after Effective Lifetime'),
('CWE-776', 'Improper Restriction of Recursive Entity References in DTDs (''XML Entity Expansion'')'),
('CWE-787', 'Out-of-bounds Write'),
('CWE-798', 'Use of Hard-coded Credentials'),
('CWE-799', 'Improper Control of Interaction Frequency'),
('CWE-807', 'Reliance on Untrusted Inputs in a Security Decision'),
('CWE-829', 'Inclusion of Functionality from Untrusted Control Sphere'),
('CWE-835', 'Loop with Unreachable Exit Condition (''Infinite Loop'')'),
('CWE-838', 'Inappropriate Encoding for Output Context'),
('CWE-840', 'Business Logic Errors'),
('CWE-841', 'Improper Enforcement of Behavioral Workflow'),
('CWE-842', 'Placement of User into Incorrect Group'),
('CWE-862', 'Missing Authorization'),
('CWE-863', 'Incorrect Authorization'),
('CWE-913', 'Improper Control of Dynamically-Managed Code Resources'),
('CWE-915', 'Improperly Controlled Modification of Dynamically-Determined Object Attributes'),
('CWE-916', 'Use of Password Hash With Insufficient Computational Effort'),
('CWE-918', 'Server-Side Request Forgery (SSRF)'),
('CWE-922', 'Insecure Storage of Sensitive Information'),
('CWE-942', 'Permissive Cross-domain Policy with Untrusted Domains'),
('CWE-1021', 'Improper Restriction of Rendered UI Layers or Frames'),
('CWE-1104', 'Use of Unmaintained Third Party Components'),
('CWE-1236', 'Improper Neutralization of Formula Elements in a CSV File'),
('CWE-1275', 'Sensitive Cookie with Improper SameSite Attribute'),
('CWE-1321', 'Improperly Controlled Modification of Object Prototype Attributes (''Prototype Pollution'')'),
('CWE-1333', 'Inefficient Regular Expression Complexity'),
('CWE-1336', 'Improper Neutralization of Special Elements Used in a Template Engine'),
('CWE-1390', 'Weak Authentication'),
('CWE-1391', 'Use of Weak Credentials'),
('CWE-1394', 'Use of Default Cryptographic Key'),
('CWE-1395', 'Dependency on Vulnerable Third-Party Component');


SET FOREIGN_KEY_CHECKS = 1;
