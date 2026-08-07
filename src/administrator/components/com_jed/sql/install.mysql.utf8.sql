SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_extensions`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions`
(
	`id`                     int unsigned    NOT NULL AUTO_INCREMENT,
	`name`                   varchar(255)    NOT NULL DEFAULT '',
	`alias`                  varchar(255)    NOT NULL DEFAULT '',
	`catid`                  int unsigned    NOT NULL,
	`owner`                  int unsigned    NOT NULL,
	`state`                  tinyint(1)      NOT NULL DEFAULT '0',
	`approved`               tinyint(1)      DEFAULT '0',
	`approved_time`          datetime        DEFAULT NULL,
	-- The moderation verdict (P1-02): approved_time NULL means "not decided yet", set together
	-- with approved = 1 means approved, set together with approved = 0 means rejected.
	-- approved_reason carries a code from #__jed_block_reasons - one vocabulary for rejections
	-- and blocks - and approved_notes the free text sent to the developer with it.
	`approved_by`            int unsigned    DEFAULT NULL,
	`approved_notes`         text,
	`approved_reason`        varchar(255)    DEFAULT '',
	`intro`                  text,
	`description`            mediumtext,
	`license`                varchar(255)    DEFAULT '',
	`requires_registration`  tinyint(1)      NOT NULL DEFAULT '0',
	`type`                   enum('free', 'paid', 'freemium', 'cloud') DEFAULT 'free',
	`extension_types`        varchar(255)    DEFAULT '',
	`created`                datetime,
	`created_by`             int unsigned    NOT NULL,
	`modified`               datetime        DEFAULT NULL,
	`modified_by`            int unsigned    NULL,
	`checked_out`            int unsigned,
	`checked_out_time`       datetime        DEFAULT NULL,
	`extension_version`      varchar(50)     DEFAULT '',
	`entry_version`          int,
	`joomla_versions`        varchar(255)    DEFAULT '',
	`download_url`           varchar(255)    DEFAULT '',
	`support_url`            varchar(255)    DEFAULT '',
	`demo_url`               varchar(255)    DEFAULT '',
	`documentation_url`      varchar(255)    DEFAULT '',
	`git_url`                varchar(255)    DEFAULT '',
	`internal_download_url`  varchar(255)    DEFAULT '',
	`download_key`           varchar(255)    DEFAULT '',
	`uses_updater`           tinyint(1)      NOT NULL DEFAULT '0',
	`update_url`             varchar(255)    DEFAULT '',
	`last_update_check`      datetime        DEFAULT NULL,
	`last_update_check_error` varchar(255)   DEFAULT NULL,
	`developer_url`          varchar(255)    DEFAULT '',
	`developer_email`        varchar(255)    DEFAULT '',
	`changelog_url`          varchar(255)    DEFAULT '',
	`score_overall`          decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_functionality`    decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_ease_of_use`      decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_support`          decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_documentation`    decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_value_for_money`  decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_count`            int unsigned    NOT NULL DEFAULT '0',
	`popular`                tinyint(1)      NOT NULL DEFAULT '0',
	`logo`                   varchar(255)    DEFAULT '',
	`overview_image`         varchar(255)    DEFAULT '',
	`video`                  varchar(255)    DEFAULT '',
	-- The raw value above is kept as the developer typed it; these two are what P1-11's
	-- parser made of it. Provider plus id rather than a ready-made embed URL: the card view
	-- wants a thumbnail without an iframe, and that needs the id on its own. NULL means the
	-- value could not be recognised as a video, which is a result and not a gap - those rows
	-- are what the clean-up report lists.
	`video_provider`         varchar(20)     DEFAULT NULL,
	`video_id`               varchar(255)    DEFAULT NULL,
	`internal_note`          mediumtext,
	-- Blocking and soft delete are separate carriers from `state` on purpose (4.8, P1-01):
	-- `state` belongs to the developer, `blocked` to the JED team. Mapped onto one column, a
	-- developer could lift a block by republishing. block_reason_text is internal - only the
	-- reason code's title is ever shown publicly.
	`blocked`                tinyint(1)      NOT NULL DEFAULT '0',
	`block_reason_code`      varchar(32)     DEFAULT NULL,
	`block_reason_text`      text,
	`blocked_by`             int unsigned    DEFAULT NULL,
	`blocked_time`           datetime        DEFAULT NULL,
	`deleted`                tinyint(1)      NOT NULL DEFAULT '0',
	`deleted_by`             int unsigned    DEFAULT NULL,
	`deleted_time`           datetime        DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `IDX_jed_extensions_catid` (`catid`),
	KEY `IDX_jed_extensions_owner` (`owner`),
	KEY `IDX_jed_extensions_state` (`state`),
	KEY `IDX_jed_extensions_visibility` (`approved`, `state`, `blocked`, `deleted`),
	KEY `IDX_jed_extensions_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_history`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_history`
(
	`id`                     int             NOT NULL AUTO_INCREMENT,
	`extension_id`           int unsigned    NOT NULL,
	`active`                 tinyint(1)      NOT NULL DEFAULT '0',
	`name`                   varchar(255)    NOT NULL DEFAULT '',
	`alias`                  varchar(255)    NOT NULL DEFAULT '',
	`catid`                  int unsigned    NOT NULL,
	`owner`                  int unsigned    NOT NULL,
	`state`                  tinyint(1)      NOT NULL DEFAULT '0',
	`approved`               tinyint(1)      DEFAULT '0',
	`approved_time`          datetime        DEFAULT NULL,
	-- The moderation verdict (P1-02): approved_time NULL means "not decided yet", set together
	-- with approved = 1 means approved, set together with approved = 0 means rejected.
	-- approved_reason carries a code from #__jed_block_reasons - one vocabulary for rejections
	-- and blocks - and approved_notes the free text sent to the developer with it.
	`approved_by`            int unsigned    DEFAULT NULL,
	`approved_notes`         text,
	`approved_reason`        varchar(255)    DEFAULT '',
	`intro`                  text,
	`description`            mediumtext,
	`license`                varchar(255)    DEFAULT '',
	`requires_registration`  tinyint(1)      NOT NULL DEFAULT '0',
	`type`                   enum('free', 'paid', 'freemium', 'cloud') DEFAULT 'free',
	`extension_types`        varchar(255)    DEFAULT '',
	`created`                datetime,
	`created_by`             int unsigned    NOT NULL,
	`modified`               datetime        DEFAULT NULL,
	`modified_by`            int unsigned    NULL,
	`checked_out`            int unsigned,
	`checked_out_time`       datetime        DEFAULT NULL,
	`extension_version`      varchar(50)     DEFAULT '',
	`joomla_versions`        varchar(255)    DEFAULT '',
	`download_url`           varchar(255)    DEFAULT '',
	`support_url`            varchar(255)    DEFAULT '',
	`demo_url`               varchar(255)    DEFAULT '',
	`documentation_url`      varchar(255)    DEFAULT '',
	`git_url`                varchar(255)    DEFAULT '',
	`internal_download_url`  varchar(255)    DEFAULT '',
	`download_key`           varchar(255)    DEFAULT '',
	`uses_updater`           tinyint(1)      NOT NULL DEFAULT '0',
	`update_url`             varchar(255)    DEFAULT '',
	`developer_url`          varchar(255)    DEFAULT '',
	`developer_email`        varchar(255)    DEFAULT '',
	`changelog_url`          varchar(255)    DEFAULT '',
	`score_overall`          decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_functionality`    decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_ease_of_use`      decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_support`          decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_documentation`    decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_value_for_money`  decimal(3, 2)   NOT NULL DEFAULT '0.00',
	`score_count`            int unsigned    NOT NULL DEFAULT '0',
	`popular`                tinyint(1)      NOT NULL DEFAULT '0',
	`logo`                   varchar(255)    DEFAULT '',
	`overview_image`         varchar(255)    DEFAULT '',
	`video`                  varchar(255)    DEFAULT '',
	-- The raw value above is kept as the developer typed it; these two are what P1-11's
	-- parser made of it. Provider plus id rather than a ready-made embed URL: the card view
	-- wants a thumbnail without an iframe, and that needs the id on its own. NULL means the
	-- value could not be recognised as a video, which is a result and not a gap - those rows
	-- are what the clean-up report lists.
	`video_provider`         varchar(20)     DEFAULT NULL,
	`video_id`               varchar(255)    DEFAULT NULL,
	`internal_note`          mediumtext,
	-- Mirrored from #__jed_extensions. This is where the block history lives: every block and
	-- unblock writes a revision, so who blocked what, when, under which code and with which
	-- internal note is answerable from the revision list without a dedicated log table.
	`blocked`                tinyint(1)      NOT NULL DEFAULT '0',
	`block_reason_code`      varchar(32)     DEFAULT NULL,
	`block_reason_text`      text,
	`blocked_by`             int unsigned    DEFAULT NULL,
	`blocked_time`           datetime        DEFAULT NULL,
	`deleted`                tinyint(1)      NOT NULL DEFAULT '0',
	`deleted_by`             int unsigned    DEFAULT NULL,
	`deleted_time`           datetime        DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `IDX_jed_extensions_catid` (`catid`),
	KEY `IDX_jed_extensions_owner` (`owner`),
	KEY `IDX_jed_extensions_state` (`state`),
	KEY `IDX_jed_extensions_alias` (`alias`),
	KEY `IDX_jed_extensions_history_extension_id` (`extension_id`),
	KEY `IDX_jed_extensions_history_extension_id_active` (`extension_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_category_map`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_category_map`
(
	`extension_id` int unsigned NOT NULL,
	`catid`        int unsigned NOT NULL,
	PRIMARY KEY (`extension_id`, `catid`),
	KEY `IDX_jed_extensions_category_map_catid` (`catid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_maintainers`;

-- Additional maintainers only. The owner lives in #__jed_extensions.owner and is never
-- duplicated here (8.8) - "owner OR a row in this table" is the permission rule, so a person in
-- both places would be a second, silently divergent record of the same fact. The application
-- enforces it on write; a cross-table condition cannot be a MySQL constraint.
--
-- `state` is the invitation: 0 invited, 1 accepted, -1 declined. Only an accepted row grants
-- anything. The privileges reach far - edit, publish, answer reviews - and the name can appear on
-- the public listing, so being named by somebody else is not enough (P1-03 item 4). This is a
-- single confirmation; the dual one is reserved for ownership transfer, where the stakes differ.
CREATE TABLE IF NOT EXISTS `#__jed_extensions_maintainers`
(
	`extension_id`  int unsigned NOT NULL,
	`user_id`       int unsigned NOT NULL,
	`state`         tinyint(1)   NOT NULL DEFAULT '0',
	`invited_by`    int unsigned DEFAULT NULL,
	`invited_time`  datetime     DEFAULT NULL,
	`accepted_time` datetime     DEFAULT NULL,
	PRIMARY KEY (`extension_id`, `user_id`),
	KEY `IDX_jed_maintainers_user_state` (`user_id`, `state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_images`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_images`
(
	`id`               int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`     int unsigned NOT NULL,
	`filename`         text,
	`state`            tinyint(1)   DEFAULT '0',
	`ordering`         int          DEFAULT '0',
	`checked_out`      int unsigned,
	`checked_out_time` datetime,
	`created_by`       int unsigned NOT NULL,
	`modified_by`      int unsigned NULL,
	PRIMARY KEY (`id`),
	KEY `FK_jed_extensions_images_extension_id` (`extension_id`),
	KEY `FK_jed_extensions_images_created_by` (`created_by`),
	KEY `FK_jed_extensions_images_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_files`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_files`
(
	`id`            int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`  int unsigned NOT NULL,
	`file`          varchar(255) NOT NULL DEFAULT '',
	`meta`          text,
	`created_by`    int unsigned NOT NULL,
	`modified_by`   int unsigned NULL,
	`originalFile`  varchar(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `FK_jed_extensions_files_extension_id` (`extension_id`),
	KEY `FK_jed_extensions_files_created_by` (`created_by`),
	KEY `FK_jed_extensions_files_modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_reviews`;

CREATE TABLE IF NOT EXISTS `#__jed_reviews`
(
	`id`                      int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`            int unsigned NOT NULL,
	`title`                   varchar(400) DEFAULT '',
	`alias`                   varchar(400) DEFAULT NULL,
	`body`                    mediumtext,
	`functionality`           decimal(2,1) DEFAULT NULL,
	`functionality_comment`   text,
	`ease_of_use`             decimal(2,1) DEFAULT NULL,
	`ease_of_use_comment`     text,
	`support`                 decimal(2,1) DEFAULT NULL,
	`support_comment`         text,
	`documentation`           decimal(2,1) DEFAULT NULL,
	`documentation_comment`   text,
	`value_for_money`         decimal(2,1) DEFAULT NULL,
	`value_for_money_comment` text,
	`overall_score`           decimal(2,1) DEFAULT NULL,
	`used_for`                varchar(400) DEFAULT '',
	`version`                 varchar(255) DEFAULT NULL,
	`flagged`                 varchar(255) DEFAULT '0',
	`ip_address`              varchar(255) DEFAULT '',
	`state`                   tinyint(1)   NOT NULL DEFAULT '0',
	`created_on`              datetime     DEFAULT NULL,
	`created_by`              int unsigned NOT NULL,
	`ordering`                int          DEFAULT '0',
	`checked_out`             int unsigned,
	`checked_out_time`        datetime,
	`developer_response`           mediumtext,
	`developer_responded_on`       datetime     DEFAULT NULL,
	`developer_response_published` tinyint(1)   NOT NULL DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `IDX_jed_reviews_extension_id_created_by` (`extension_id`, `created_by`),
	KEY `FK_jed_reviews_extension_id` (`extension_id`),
	KEY `FK_jed_reviews_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_favorites`;

CREATE TABLE IF NOT EXISTS `#__jed_favorites`
(
	`id`           int unsigned NOT NULL AUTO_INCREMENT,
	`user_id`      int unsigned NOT NULL,
	`extension_id` int unsigned NOT NULL,
	`created`      datetime     NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `UK_jed_favorites_user_id_extension_id` (`user_id`, `extension_id`),
	KEY `FK_jed_favorites_user_id` (`user_id`),
	KEY `FK_jed_favorites_extension_id` (`extension_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_user_access`;

-- The abuse-prevention layer JED3 grew over years and this code had none of (7.1). Modelled on
-- the legacy `jed_user_access`, plus the audit fields 4.8 asks for everywhere.
--
-- **An absent row means "default privileges, not banned".** There is deliberately no row per
-- user: 14,000 rows that all say "yes to everything" would be a table nobody could read, and
-- every new registration would need one. A row exists only where something was decided.
--
-- The ban is time-limited by design: `banned_from`/`banned_until` are compared against now, so
-- a ban that has run out stops applying by itself. Making that depend on a cleanup job would
-- mean a job that fails silently leaves people banned.
CREATE TABLE IF NOT EXISTS `#__jed_user_access`
(
	`user_id`                 int unsigned NOT NULL,
	`create_listing`          tinyint(1)   NOT NULL DEFAULT '1',
	`edit_listing`            tinyint(1)   NOT NULL DEFAULT '1',
	`update_xml`              tinyint(1)   NOT NULL DEFAULT '1',
	`review`                  tinyint(1)   NOT NULL DEFAULT '1',
	`report`                  tinyint(1)   NOT NULL DEFAULT '1',
	`auto_approve_extensions` tinyint(1)   NOT NULL DEFAULT '0',
	`auto_approve_reviews`    tinyint(1)   NOT NULL DEFAULT '0',
	`banned`                  tinyint(1)   NOT NULL DEFAULT '0',
	`banned_reason`           text,
	`banned_from`             datetime     DEFAULT NULL,
	`banned_until`            datetime     DEFAULT NULL,
	`set_by`                  int unsigned DEFAULT NULL,
	`set_time`                datetime     DEFAULT NULL,
	PRIMARY KEY (`user_id`),
	KEY `IDX_jed_user_access_banned` (`banned`, `banned_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_user_review_bans`;

-- Barred from reviewing a particular developer or anything in a particular category, rather than
-- barred from reviewing at all. JED3 kept these as two mapping tables; one table with a target
-- type says the same thing and keeps the check to a single query.
CREATE TABLE IF NOT EXISTS `#__jed_user_review_bans`
(
	`user_id`     int unsigned                  NOT NULL,
	`target_type` enum('developer','category')  NOT NULL,
	`target_id`   int unsigned                  NOT NULL,
	`set_by`      int unsigned                  DEFAULT NULL,
	`set_time`    datetime                      DEFAULT NULL,
	PRIMARY KEY (`user_id`, `target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_suspect_ip_ranges`;

-- Advisory only (P1-05 item 8): a match flags, it never blocks. A shared NAT range is not
-- evidence of anything, and treating it as such would lock out a whole office or country.
-- varbinary(16) holds IPv4 and IPv6 alike through INET6_ATON, so one comparison covers both.
CREATE TABLE IF NOT EXISTS `#__jed_suspect_ip_ranges`
(
	`id`          int unsigned  NOT NULL AUTO_INCREMENT,
	`range_start` varbinary(16) NOT NULL,
	`range_end`   varbinary(16) NOT NULL,
	`note`        varchar(255)  DEFAULT NULL,
	`state`       tinyint(1)    NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`),
	KEY `IDX_jed_suspect_ip_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extension_transfers`;

-- A transfer is a state over time, so it is a record of its own rather than columns on the
-- extension (8.8.1). The completed row stays: it is the traceability requirement, and it is the
-- only place that records who handed what to whom.
--
-- States: pending -> from_confirmed / to_confirmed -> completed, plus expired, cancelled and
-- forced. Ownership moves only when *both* parties have confirmed, in either order - which is
-- why the two confirmations are two nullable timestamps rather than one flag.
--
-- The tokens are credentials and are stored **hashed only**. Reading this table must not enable
-- a transfer. They are bound to the user id rather than the email address, so changing an
-- address mid-flight does not break a transfer that is already under way.
CREATE TABLE IF NOT EXISTS `#__jed_extension_transfers`
(
	`id`                  int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`        int unsigned NOT NULL,
	`from_user_id`        int unsigned NOT NULL,
	`to_user_id`          int unsigned NOT NULL,
	`initiated_by`        int unsigned NOT NULL,
	`initiated_time`      datetime     NOT NULL,
	`from_token_hash`     varchar(255) DEFAULT NULL,
	`from_confirmed_time` datetime     DEFAULT NULL,
	`to_token_hash`       varchar(255) DEFAULT NULL,
	`to_confirmed_time`   datetime     DEFAULT NULL,
	`state`               varchar(20)  NOT NULL DEFAULT 'pending',
	`expires`             datetime     NOT NULL,
	`completed_time`      datetime     DEFAULT NULL,
	`cancelled_by`        int unsigned DEFAULT NULL,
	`cancel_reason`       text,
	PRIMARY KEY (`id`),
	KEY `IDX_transfers_extension` (`extension_id`, `state`),
	KEY `IDX_transfers_to_user` (`to_user_id`, `state`),
	KEY `IDX_transfers_expiry` (`state`, `expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_transfer_lookups`;

-- Looking a recipient up by email address tells the person asking whether that address has an
-- account. 8.8.1 weighed hiding that against making the feature unusable - an owner who cannot
-- tell a typo from a missing account is stuck - and chose the clear answer, bounded. This table
-- is the bound: every attempt is recorded with who made it, which turns an anonymous oracle into
-- an attributable one, and the count per window is what the rate limit reads.
--
-- The address is stored hashed. The log has to answer "how many attempts did this user make"
-- and "did they probe the same address repeatedly", and neither question needs the plaintext -
-- keeping a list of addresses somebody guessed at would create the disclosure it exists to bound.
CREATE TABLE IF NOT EXISTS `#__jed_transfer_lookups`
(
	`id`           int unsigned NOT NULL AUTO_INCREMENT,
	`user_id`      int unsigned NOT NULL,
	`email_hash`   varchar(64)  NOT NULL,
	`found`        tinyint(1)   NOT NULL DEFAULT '0',
	`extension_id` int unsigned DEFAULT NULL,
	`created`      datetime     NOT NULL,
	PRIMARY KEY (`id`),
	KEY `IDX_transfer_lookups_user` (`user_id`, `created`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_block_reasons`;

-- The vocabulary a moderation decision is stated in. Codes are data, not code (4.8), so the JED
-- team can add one without a release. `code` is the primary key because it is what
-- #__jed_extensions and its history store, what the knowledge base articles are keyed by, and
-- what the API will carry - a surrogate id would add a join and a second identity for the same
-- thing. Seeded by script.php from the JED3 submission error codes (P0-05), which the
-- com_tickets mail templates already speak.
--
-- The table is named for blocking because `P1-01` introduced it, but `P1-02` deliberately reuses
-- it for submission rejections: a rejection reason and a block reason say the same things to a
-- developer and are keyed by the same knowledge base articles, so one vocabulary is the point.
-- `mail_template` is the #__mail_templates id sent to the developer when a submission is
-- rejected with this code.
CREATE TABLE IF NOT EXISTS `#__jed_block_reasons`
(
	`code`          varchar(32)  NOT NULL,
	`title`         varchar(255) NOT NULL,
	`article_id`    int unsigned DEFAULT NULL,
	`mail_template` varchar(128) DEFAULT NULL,
	`state`         tinyint(1)   NOT NULL DEFAULT '1',
	`ordering`      int          NOT NULL DEFAULT '0',
	PRIMARY KEY (`code`),
	KEY `IDX_jed_block_reasons_state` (`state`, `ordering`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_joomla_versions`;


CREATE TABLE IF NOT EXISTS `#__jed_joomla_versions`
(
	`id`         int unsigned NOT NULL AUTO_INCREMENT,
	`label`      varchar(255) NOT NULL,
	`long_label` varchar(50)  NOT NULL,
	`published`  tinyint(1)   NOT NULL DEFAULT '1',
	PRIMARY KEY (`id`),
	KEY `IDX_jed_joomla_versions_published` (`published`),
	KEY `IDX_jed_joomla_versions_label` (`label`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('15','1.5','Joomla 1.5','0');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('25','2.5','Joomla 2.5','0');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('30','3','Joomla 3','1');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('40','4','Joomla 4','1');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('50','5','Joomla 5','1');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('51','5 (b/c)','Joomla 5 using B/C plugin','1');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('60','6','Joomla 6','1');
INSERT INTO `#__jed_joomla_versions` (`id`, `label`, `long_label`, `published`) VALUES('61','6 (b/c)','Joomla 6 using B/C plugin','1');

DROP TABLE IF EXISTS `#__jed_queue_jobs`;

CREATE TABLE IF NOT EXISTS `#__jed_queue_jobs`
(
	`id`             int unsigned NOT NULL AUTO_INCREMENT,
	`type`           varchar(50)  NOT NULL,
	`extension_id`   int unsigned DEFAULT NULL,
	`history_id`     int unsigned DEFAULT NULL,
	`payload`        text,
	`status`         varchar(20)  NOT NULL DEFAULT 'pending',
	`attempts`       tinyint unsigned NOT NULL DEFAULT '0',
	`last_error`     text,
	`result_meta`    text,
	`created`        datetime     NOT NULL,
	`created_by`     int unsigned NOT NULL DEFAULT '0',
	`started_time`   datetime     DEFAULT NULL,
	`finished_time`  datetime     DEFAULT NULL,
	PRIMARY KEY (`id`),
	KEY `IDX_jed_queue_jobs_status` (`status`),
	KEY `IDX_jed_queue_jobs_type` (`type`),
	KEY `IDX_jed_queue_jobs_extension_id` (`extension_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


INSERT INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_jed.audit_report', 'com_jed', '', 'COM_JED_AUDIT_REPORT_EMAIL_SUBJECT', 'COM_JED_AUDIT_REPORT_EMAIL_BODY', '', '', '{"tags":["sitename","extensionname","extensionversion","phpstanreport","claudereport"]}');


SET FOREIGN_KEY_CHECKS = 1;
