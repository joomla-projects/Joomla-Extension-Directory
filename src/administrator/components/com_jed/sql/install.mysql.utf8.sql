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
	`internal_note`          mediumtext,
	PRIMARY KEY (`id`),
	KEY `IDX_jed_extensions_catid` (`catid`),
	KEY `IDX_jed_extensions_owner` (`owner`),
	KEY `IDX_jed_extensions_state` (`state`),
	KEY `IDX_jed_extensions_visibility` (`state`, `approved`),
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
	`internal_note`          mediumtext,
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

CREATE TABLE IF NOT EXISTS `#__jed_extensions_maintainers`
(
	`extension_id` int unsigned NOT NULL,
	`user_id`      int unsigned NOT NULL,
	PRIMARY KEY (`extension_id`, `user_id`)
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
	`functionality`           decimal(2,1) DEFAULT '0.0',
	`functionality_comment`   text,
	`ease_of_use`             decimal(2,1) DEFAULT '0.0',
	`ease_of_use_comment`     text,
	`support`                 decimal(2,1) DEFAULT '0.0',
	`support_comment`         text,
	`documentation`           decimal(2,1) DEFAULT '0.0',
	`documentation_comment`   text,
	`value_for_money`         decimal(2,1) DEFAULT '0.0',
	`value_for_money_comment` text,
	`overall_score`           decimal(2,1) DEFAULT '0.0',
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
