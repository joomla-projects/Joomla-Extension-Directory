-- Feed runs in steps (the Feed Runs page shows a run as it goes instead of waiting for one long
-- request that the web server's gateway timeout cuts off).
--
-- `progress`:  the plan of a running run and how far it has got (JSON); NULL once closed.
-- `heartbeat`: the step lock; NULL between steps.
--
-- `#__vel_feed_backlog`: the CVE feed's work queue (see install.mysql.utf8.sql). Reading the delta
-- log and fetching the records it lists are separate, so a run cut short no longer drops the
-- records it did not reach.
--
-- Rename this file to the version that ships it if that is not 4.0.1, and keep it mirrored in
-- install.mysql.utf8.sql.

ALTER TABLE `#__vel_feed_runs` ADD COLUMN `progress` mediumtext AFTER `cursor` /** CAN FAIL **/;
ALTER TABLE `#__vel_feed_runs` ADD COLUMN `heartbeat` datetime DEFAULT NULL AFTER `progress` /** CAN FAIL **/;

CREATE TABLE IF NOT EXISTS `#__vel_feed_backlog`
(
	`id`          int unsigned NOT NULL AUTO_INCREMENT,
	`source`      varchar(8)   NOT NULL,
	`external_id` varchar(32)  NOT NULL,
	-- The record's raw URL from the delta log (`githubLink`), when it gave one.
	`url`         varchar(255) NOT NULL DEFAULT '',
	-- The delta entry that listed it (newest first is the fetch order).
	`fetch_time`  datetime     DEFAULT NULL,
	`queued`      datetime     NOT NULL,
	-- Failed fetches so far; at FeedBacklog::MAX_ATTEMPTS the id is set aside until it changes again.
	`attempts`    int unsigned NOT NULL DEFAULT '0',
	`last_error`  varchar(255) NOT NULL DEFAULT '',
	-- The run whose read of the log is still in progress. Staged ids are only committed (set
	-- NULL) once that read has loaded to the end, and are deleted if it does not.
	`staged_by`   int unsigned DEFAULT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_vel_feed_backlog_external` (`source`, `external_id`),
	KEY `IDX_vel_feed_backlog_next` (`source`, `staged_by`, `attempts`, `fetch_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
