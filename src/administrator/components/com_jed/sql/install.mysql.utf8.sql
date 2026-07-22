SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_extensions`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions`
(
	`id`                     int unsigned    NOT NULL AUTO_INCREMENT,
	`name`                   varchar(255)    NOT NULL DEFAULT '',
	`alias`                  varchar(255)    NOT NULL DEFAULT '',
	`catid`                  int             DEFAULT NULL,
	`owner`                  int             DEFAULT NULL,
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
	`created_by`             int             DEFAULT '0',
	`modified`               datetime        DEFAULT NULL,
	`modified_by`            int             DEFAULT '0',
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
	KEY `IDX_jed_extensions_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_history`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_history`
(
	`id`                     int             NOT NULL AUTO_INCREMENT,
	`extension_id`           int             NOT NULL,
	`active`                 tinyint(1)      NOT NULL DEFAULT '0',
	`name`                   varchar(255)    NOT NULL DEFAULT '',
	`alias`                  varchar(255)    NOT NULL DEFAULT '',
	`catid`                  int             DEFAULT NULL,
	`owner`                  int             DEFAULT NULL,
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
	`created_by`             int             DEFAULT '0',
	`modified`               datetime        DEFAULT NULL,
	`modified_by`            int             DEFAULT '0',
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
	KEY `IDX_jed_extensions_history_active` (`extension_id`, `active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_category_map`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_category_map`
(
	`extension_id` int NOT NULL,
	`catid`        int NOT NULL,
	PRIMARY KEY (`extension_id`, `catid`),
	KEY `IDX_jed_extension_category_map_catid` (`catid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_maintainers`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_maintainers`
(
	`extension_id` int NOT NULL,
	`user_id`      int NOT NULL,
	PRIMARY KEY (`extension_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_images`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_images`
(
	`id`               int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`     int unsigned DEFAULT '0',
	`filename`         text,
	`state`            tinyint(1)   DEFAULT '0',
	`ordering`         int          DEFAULT '0',
	`checked_out`      int unsigned,
	`checked_out_time` datetime,
	`created_by`       int          DEFAULT '0',
	`modified_by`      int          DEFAULT '0',
	PRIMARY KEY (`id`),
	KEY `FK_jed_extension_images` (`extension_id`),
	KEY `FK_jed_extension_images_user` (`created_by`),
	KEY `FK_jed_extension_images_moduser` (`modified_by`),
	CONSTRAINT `FKC_jed_extension_images` FOREIGN KEY (`extension_id`) REFERENCES `#__jed_extensions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `FKC_jed_extension_images_user` FOREIGN KEY (`created_by`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `FKC_jed_extension_images_moduser` FOREIGN KEY (`modified_by`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_extensions_files`;

CREATE TABLE IF NOT EXISTS `#__jed_extensions_files` (
	`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`extension_id` INT UNSIGNED NOT NULL   DEFAULT 0,
	`file` VARCHAR(255)  NOT NULL  DEFAULT '',
	`meta` TEXT,
	`created_by` INT(11) NOT NULL  DEFAULT 0,
	`originalFile` VARCHAR(255) NOT NULL DEFAULT '',
	PRIMARY KEY (`id`),
	KEY `jed_extensions_files_FK` (`extension_id`),
	CONSTRAINT `jed_extensions_files_FK` FOREIGN KEY (`extension_id`) REFERENCES `#__jed_extensions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_reviews`;

CREATE TABLE IF NOT EXISTS `#__jed_reviews`
(
	`id`                      int unsigned NOT NULL AUTO_INCREMENT,
	`extension_id`            int unsigned DEFAULT '0',
	`title`                   varchar(400) DEFAULT '',
	`alias`                   varchar(400) DEFAULT NULL,
	`body`                    mediumtext,
	`functionality`           int DEFAULT '0',
	`functionality_comment`   text,
	`ease_of_use`             int DEFAULT '0',
	`ease_of_use_comment`     text,
	`support`                 int DEFAULT '0',
	`support_comment`         text,
	`documentation`           int DEFAULT '0',
	`documentation_comment`   text,
	`value_for_money`         int DEFAULT '0',
	`value_for_money_comment` text,
	`overall_score`           int DEFAULT '0',
	`used_for`                varchar(400) DEFAULT '',
	`version`                 varchar(255) DEFAULT NULL,
	`flagged`                 varchar(255) DEFAULT '0',
	`ip_address`              varchar(255) DEFAULT '',
	`published`               int DEFAULT '0',
	`created_on`              datetime     DEFAULT NULL,
	`created_by`              int          DEFAULT '0',
	`ordering`                int          DEFAULT '0',
	`checked_out`             int unsigned,
	`checked_out_time`        datetime,
	PRIMARY KEY (`id`),
	UNIQUE KEY `UK_jed_reviews_ext_user` (`extension_id`, `created_by`),
	KEY `FK_jed_reviews` (`extension_id`),
	KEY `FK_jed_reviews_user` (`created_by`),
	CONSTRAINT `FKC_jed_reviews` FOREIGN KEY (`extension_id`) REFERENCES `#__jed_extensions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `FKC_jed_reviews_user` FOREIGN KEY (`created_by`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `#__jed_favorites`;

CREATE TABLE IF NOT EXISTS `#__jed_favorites`
(
	`id`           int unsigned NOT NULL AUTO_INCREMENT,
	`user_id`      int          NOT NULL,
	`extension_id` int          NOT NULL,
	`created`      datetime     NOT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `UK_jed_favorites_user_extension` (`user_id`, `extension_id`),
	KEY `FK_jed_favorites_user` (`user_id`),
	KEY `FK_jed_favorites_extension` (`extension_id`),
	CONSTRAINT `FKC_jed_favorites_user` FOREIGN KEY (`user_id`) REFERENCES `#__users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
	CONSTRAINT `FKC_jed_favorites_extension` FOREIGN KEY (`extension_id`) REFERENCES `#__jed_extensions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `#__jed_developers`
(
    `id`             int unsigned NOT NULL AUTO_INCREMENT,
    `user_id`        int          DEFAULT NULL,
    `developer_name` varchar(150) DEFAULT NULL,
    `suspicious`     tinyint(1)   DEFAULT '0',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `#__jed_joomla_versions`
(
    `id`         int(10) unsigned NOT NULL AUTO_INCREMENT,
    `label`      varchar(255)     NOT NULL,
    `long_label` varchar(50)      NOT NULL,
    `published`  tinyint(4)       NOT NULL DEFAULT 1,
    PRIMARY KEY (`id`),
    KEY `inx_published` (`published`),
    KEY `inx_label` (`label`)
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
	KEY `IDX_jed_queue_jobs_extension` (`extension_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


INSERT INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_jed.audit_report', 'com_jed', '', 'COM_JED_AUDIT_REPORT_EMAIL_SUBJECT', 'COM_JED_AUDIT_REPORT_EMAIL_BODY', '', '', '{"tags":["sitename","extensionname","extensionversion","phpstanreport","claudereport"]}');


SET FOREIGN_KEY_CHECKS = 1;
