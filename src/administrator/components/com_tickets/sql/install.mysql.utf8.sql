-- Safe to run twice (P1-30): CREATE TABLE IF NOT EXISTS, INSERT IGNORE for the seed rows, and no
-- DROP - those moved to reset.mysql.utf8.sql, which no manifest references. Schema changes after
-- go-live belong in sql/updates/mysql/<version>.sql; this file is kept in step so that a fresh
-- install ends up with the same schema.

/* Ticket Category Types */

CREATE TABLE IF NOT EXISTS `#__jed_ticket_categories`
(
    `id`               int unsigned NOT NULL AUTO_INCREMENT,
    `categorytype`     varchar(255) DEFAULT '',
    `ordering`         int          DEFAULT '0',
    `state`            tinyint(1)   DEFAULT '1',
    `checked_out`      int unsigned,
    `checked_out_time` datetime,
    `created_by`       int unsigned NOT NULL,
    `modified_by`      int unsigned NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__jed_ticket_categories`(`id`, `categorytype`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Unknown', 0, 1, NULL, NULL, 652, 652),
(2, 'Extension', 0, 1, NULL, NULL, 652, 652),
(3, 'Review', 0, 1, NULL, NULL, 652, 652),
(4, 'Joomla Site Issue', 0, 1, NULL, NULL, 652, 652),
(5, 'New Listing Support', 0, 1, NULL, NULL, 652, 652),
(6, 'Current Listing Support', 0, 1, NULL, NULL, 652, 652),
(7, 'Site Technical Issues', 0, 1, NULL, NULL, 652, 652),
(8, 'Unpublished Support', 0, 1, NULL, NULL, 652, 652),
(9, 'Reported Review', 0, 1, NULL, NULL, 652, 652),
(10, 'Reported Extension', 0, 1, NULL, NULL, 652, 652);

/* Ticket Allocation Groups */
CREATE TABLE IF NOT EXISTS `#__jed_ticket_groups`
(
    `id`               int unsigned NOT NULL AUTO_INCREMENT,
    `name`             varchar(255) DEFAULT '',
    `ordering`         int          DEFAULT '0',
    `state`            tinyint(1)   DEFAULT '1',
    `checked_out`      int unsigned,
    `checked_out_time` datetime,
    `created_by`       int unsigned NOT NULL,
    `modified_by`      int unsigned NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__jed_ticket_groups`(`id`, `name`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Any', 0, 1, NULL, NULL, 652, 652),
(2, 'Team Leadership', 0, 1, NULL, NULL, 652, 652),
(3, 'Listing Specialist', 0, 1, NULL, NULL, 652, 652),
(4, 'Review Specialist', 0, 1, NULL, NULL, 652, 652),
(5, 'Support Speciailist', 0, 1, NULL, NULL, 652, 652);

/* Ticket Linked Items */
CREATE TABLE IF NOT EXISTS `#__jed_ticket_linked_item_types`
(
    `id`               int unsigned NOT NULL AUTO_INCREMENT,
    `title`            varchar(255) DEFAULT '',
    `model`            varchar(255) DEFAULT '',
    `ordering`         int          DEFAULT '0',
    `state`            tinyint(1)   DEFAULT '1',
    `checked_out`      int unsigned,
    `checked_out_time` datetime,
    `created_by`       int unsigned NOT NULL,
    `modified_by`      int unsigned NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `#__jed_ticket_linked_item_types`(`id`, `title`, `model`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Unknown', 'unknown', 0, 1, NULL, NULL, 652, 652),
(2, 'Extension', 'Extension', 1, 1, NULL, NULL, 652, 652),
(3, 'Review', 'Review', 0, 1, NULL, NULL, 652, 652);

/* JED Ticket Messages */
CREATE TABLE IF NOT EXISTS `#__jed_ticket_messages`
(
    `id`                int unsigned NOT NULL AUTO_INCREMENT,
    `ticket_id`         int          DEFAULT '0',
    `subject`           varchar(255) NOT NULL,
    `message`           text,
    `message_direction` int          DEFAULT '0',
    `internal`          tinyint(1)   DEFAULT '0',
    `created_by`        int unsigned NOT NULL,
    `created_on`        datetime     DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

/* JED Tickets */
CREATE TABLE IF NOT EXISTS `#__jed_tickets`
(
    `id`                      int unsigned NOT NULL AUTO_INCREMENT,
    `ticket_origin`           varchar(255) NULL DEFAULT '0',
    `ticket_category_type`    int          NULL DEFAULT 0,
    `ticket_subject`          varchar(255) NULL DEFAULT '',
    `ticket_text`             text         NULL,
    `internal_notes`          text         NULL,
    `uploaded_files_preview`  blob         NULL,
    `uploaded_files_location` varchar(255) NULL DEFAULT '',
    `allocated_group`         int          NULL DEFAULT 0,
    `allocated_to`            int          NULL DEFAULT 0,
    `linked_item_type`        int          NULL DEFAULT 0,
    `linked_item_id`          int          NULL DEFAULT 0,
    `ticket_status`           varchar(255) NULL DEFAULT '0',
    `parent_id`               int          NULL DEFAULT 0,
    `state`                   tinyint(1)   NOT NULL DEFAULT '1',
    `ordering`                int          NULL DEFAULT 0,
    `created_by`              int unsigned NOT NULL,
    `created_on`              datetime,
    `modified_by`             int unsigned NULL,
    `modified_on`             datetime,
    `checked_out`             int unsigned,
    `checked_out_time`        datetime,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
