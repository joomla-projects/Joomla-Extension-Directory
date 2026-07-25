/* Ticket Category Types */
DROP TABLE IF EXISTS `#__jed_ticket_categories`;

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

INSERT INTO `#__jed_ticket_categories`(`id`, `categorytype`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Unknown', 0, 1, NULL, NULL, 652, 652),
(2, 'Extension', 0, 1, NULL, NULL, 652, 652),
(3, 'Review', 0, 1, NULL, NULL, 652, 652),
(4, 'Joomla Site Issue', 0, 1, NULL, NULL, 652, 652),
(5, 'New Listing Support', 0, 1, NULL, NULL, 652, 652),
(6, 'Current Listing Support', 0, 1, NULL, NULL, 652, 652),
(7, 'Site Technical Issues', 0, 1, NULL, NULL, 652, 652),
(8, 'Unpublished Support', 0, 1, NULL, NULL, 652, 652),
(9, 'Reported Review', 0, 1, NULL, NULL, 652, 652),
(10, 'Reported Extension', 0, 1, NULL, NULL, 652, 652),
(11, 'Vulnerable Item Report', 0, 1, NULL, NULL, 652, 652),
(12, 'VEL Developer Update', 0, 1, NULL, NULL, 652, 652),
(13, 'VEL Abandonware Report', 0, 1, NULL, NULL, 652, 652);

/* Ticket Allocation Groups */
DROP TABLE IF EXISTS `#__jed_ticket_groups`;
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

INSERT INTO `#__jed_ticket_groups`(`id`, `name`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Any', 0, 1, NULL, NULL, 652, 652),
(2, 'Team Leadership', 0, 1, NULL, NULL, 652, 652),
(3, 'Listing Specialist', 0, 1, NULL, NULL, 652, 652),
(4, 'Review Specialist', 0, 1, NULL, NULL, 652, 652),
(5, 'Support Speciailist', 0, 1, NULL, NULL, 652, 652),
(6, 'VEL Specialist', 0, 1, NULL, NULL, 652, 652);

/* Ticket Linked Items */
DROP TABLE IF EXISTS `#__jed_ticket_linked_item_types`;
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

INSERT INTO `#__jed_ticket_linked_item_types`(`id`, `title`, `model`, `ordering`, `state`, `checked_out`, `checked_out_time`, `created_by`, `modified_by`) VALUES
(1, 'Unknown', 'unknown', 0, 1, NULL, NULL, 652, 652),
(2, 'Extension', 'Extension', 1, 1, NULL, NULL, 652, 652),
(3, 'Review', 'Review', 0, 1, NULL, NULL, 652, 652),
(4, 'Vulnerable Item Initial Report', 'Velreport', 0, 1, NULL, NULL, 652, 652),
(5, 'Vulnerable Item Developer Update', 'Veldeveloperupdate', 0, 1, NULL, NULL, 652, 652),
(6, 'VEL Abandonware Report', 'Velabandonedreport', 0, 1, NULL, NULL, 652, 652);

/* JED Ticket Messages */
DROP TABLE IF EXISTS `#__jed_ticket_messages`;
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
DROP TABLE IF EXISTS `#__jed_tickets`;
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
