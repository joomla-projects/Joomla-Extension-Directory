-- Prepare the legacy status history import (P1-24 open question 1, answered: all three tables go
-- into #__jed_extensions_history as revisions).
--
-- JED3 logged what happened to a listing in three places and none of them is a superset of the
-- others:
--
--   wqyh6_jed_extensions_status   65,018 rows   the moderation log: type + reason codes + message
--   wqyh6_jed_audit              589,661 rows   a general audit trail; 111,959 rows concern an
--                                               extension, the rest are logins, profile saves,
--                                               reviews and tickets
--   wqyh6_jed_edit_log                55 rows   status and title changes, keyed on UCM content id
--
-- They overlap far less than their event names suggest: only 5,133 audit rows share an extension,
-- a type and a timestamp to the second with a status row. Those are dropped as literal duplicates;
-- everything else from both tables is a separate event and is kept.
--
-- WHAT CANNOT COME ACROSS. #__jed_extensions_history is keyed on extension_id, so an event that
-- names no extension cannot become a revision at all. That excludes 477,702 audit rows -
-- user.login (372,572), user.save (87,118), review.* and ticket.* - and 4,576 extension events
-- whose listing did not survive the import. This is a limit of the target shape, not a choice:
-- a login is not a version of a listing. Recorded as accepted loss in P1-24.
--
-- WHAT THIS COSTS. Roughly 165,000 revisions on top of the 105,243 the ucm_history import already
-- writes, each carrying a copy of the listing description - about half a gigabyte on the real
-- data, and the "versions" count in the admin list becomes a count of everything that ever
-- happened to a listing rather than a count of edits. That is the shape the decision asks for; it
-- is noted here because it is not visible from the step name.
--
-- Everything is normalised into one staging table first, so that the batching, the de-duplication
-- and the reason-code parsing each happen once rather than three times.
DROP TABLE IF EXISTS combine_jed_status_events;

-- The collation is pinned to the one the component tables use. first_code is compared against
-- #__jed_block_reasons.code in the import step, and two utf8mb4 columns with different collations
-- are not comparable at all - MariaDB raises "illegal mix of collations" rather than picking one.
CREATE TABLE combine_jed_status_events (seq BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, extension_id INT UNSIGNED NOT NULL, event_time DATETIME NULL DEFAULT NULL, user_id INT UNSIGNED NULL DEFAULT NULL, source VARCHAR(6) NOT NULL, event_type VARCHAR(50) NOT NULL, codes VARCHAR(255) NULL DEFAULT NULL, first_code VARCHAR(32) NULL DEFAULT NULL, message TEXT NULL, PRIMARY KEY (seq), KEY idx_status_events_ext (extension_id)) ENGINE=INNODB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

-- The moderation log. `code` is a JSON array - ["PE1"], ["PH1","GL1","PH2"], [] or NULL - so the
-- first entry is taken as the reason code and the whole array is kept as text for the note.
INSERT INTO combine_jed_status_events (extension_id, event_time, user_id, source, event_type, codes, first_code, message) SELECT s.extension_id, NULLIF(s.created_time, '0000-00-00 00:00:00'), NULLIF(s.user_id, 0), 'status', LEFT(IFNULL(s.type, ''), 50), LEFT(NULLIF(IFNULL(s.code, ''), '[]'), 255), CASE WHEN JSON_VALID(s.code) AND JSON_LENGTH(s.code) > 0 THEN LEFT(JSON_UNQUOTE(JSON_EXTRACT(s.code, '$[0]')), 32) END, s.message FROM wqyh6_jed_extensions_status s INNER JOIN #__jed_extensions e ON e.id = s.extension_id WHERE TRIM(IFNULL(s.type, '')) <> '';

-- The de-duplication key of everything the moderation log just contributed, as its own table with
-- the key as the primary key.
--
-- Both halves of this matter. Matching the audit rows against wqyh6_jed_extensions_status directly
-- gives the source nothing to look them up by, and 111,959 audit rows each scanning a 65,018-row
-- table does not finish inside any request budget - the first version of this file had to be
-- killed after ten minutes. And it cannot be a self-join onto combine_jed_status_events either,
-- because that is the table being inserted into.
DROP TABLE IF EXISTS combine_jed_status_seen;

CREATE TABLE combine_jed_status_seen (extension_id INT UNSIGNED NOT NULL, event_time DATETIME NOT NULL, event_type VARCHAR(50) NOT NULL, PRIMARY KEY (extension_id, event_time, event_type)) ENGINE=INNODB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO combine_jed_status_seen (extension_id, event_time, event_type) SELECT ev.extension_id, ev.event_time, ev.event_type FROM combine_jed_status_events ev WHERE ev.source = 'status' AND ev.event_time IS NOT NULL;

-- The audit trail. The event name carries the reason code inside it rather than beside it -
-- "extension.unpublished.UR9" and even "extension.unpublished.UR9.UR12" - so the type is the first
-- two segments and whatever follows is the code list.
--
-- The LEFT JOIN drops the 5,133 audit rows that repeat a moderation-log entry exactly: same
-- listing, same type, same second. Anything else is a separate event even where the names match,
-- because the two logs were written by different code paths at different times.
INSERT INTO combine_jed_status_events (extension_id, event_time, user_id, source, event_type, codes, first_code, message) SELECT a.event_item_id, NULLIF(a.created_time, '0000-00-00 00:00:00'), NULLIF(a.user_id, 0), 'audit', LEFT(SUBSTRING_INDEX(a.event, '.', 2), 50), NULLIF(LEFT(SUBSTRING(a.event, CHAR_LENGTH(SUBSTRING_INDEX(a.event, '.', 2)) + 2), 255), ''), NULLIF(LEFT(SUBSTRING_INDEX(SUBSTRING(a.event, CHAR_LENGTH(SUBSTRING_INDEX(a.event, '.', 2)) + 2), '.', 1), 32), ''), a.message FROM wqyh6_jed_audit a INNER JOIN #__jed_extensions e ON e.id = a.event_item_id LEFT JOIN combine_jed_status_seen s ON s.extension_id = a.event_item_id AND s.event_time = a.created_time AND s.event_type = SUBSTRING_INDEX(a.event, '.', 2) WHERE a.event LIKE 'extension.%' AND s.extension_id IS NULL;

-- The edit log. Its key column is called ucm_core_content_id but holds the **extension** id, not a
-- UCM one: both readings match all 55 rows because the id ranges overlap, and the tie is broken by
-- the log's own title_changed_from - row 1 says "DevArt Article Photo", which is extension 17290;
-- UCM content 17290 is a review called "MAGNIFICENT SUPPORT". Joining on the name would have
-- attached every one of these events to the wrong listing.
--
-- Its two before/after pairs have no target columns, so they are written into the message - the
-- only place a revision can say what changed, given the revision itself carries the listing as it
-- is now. 47 of the 55 rows record neither a status nor a title change and leave it empty.
INSERT INTO combine_jed_status_events (extension_id, event_time, user_id, source, event_type, codes, first_code, message) SELECT e.id, NULLIF(l.timestamp, '0000-00-00 00:00:00'), NULLIF(l.user_id, 0), 'edit', 'extension.edit', NULL, NULL, CONCAT_WS('; ', CASE WHEN IFNULL(l.status_changed_from, -1) <> IFNULL(l.status_changed_to, -1) THEN CONCAT('status ', IFNULL(l.status_changed_from, 0), ' -> ', IFNULL(l.status_changed_to, 0)) END, CASE WHEN IFNULL(l.title_changed_from, '') <> IFNULL(l.title_changed_to, '') THEN CONCAT('title "', IFNULL(l.title_changed_from, ''), '" -> "', IFNULL(l.title_changed_to, ''), '"') END) FROM wqyh6_jed_edit_log l INNER JOIN #__jed_extensions e ON e.id = l.ucm_core_content_id;

-- Ledger of events already written, for the same reason the ucm_history import keeps one: a
-- browser timeout does not mean the statement failed on the server, and a retry must not write
-- the same revision twice.
DROP TABLE IF EXISTS combine_jed_status_done;

CREATE TABLE combine_jed_status_done (seq BIGINT UNSIGNED NOT NULL, PRIMARY KEY (seq)) ENGINE=INNODB;

-- Equal-row-count boundaries, NTILE rather than an id range so no step inherits a disproportionate
-- share of the work.
DROP TABLE IF EXISTS combine_jed_status_batches;

CREATE TABLE combine_jed_status_batches (batch INT UNSIGNED NOT NULL, lo BIGINT UNSIGNED NULL, hi BIGINT UNSIGNED NULL, PRIMARY KEY (batch)) ENGINE=INNODB;

INSERT INTO combine_jed_status_batches (batch, lo, hi) SELECT t.batch, MIN(t.seq), MAX(t.seq) FROM (SELECT ev.seq, NTILE({{STATUS_BATCHES}}) OVER (ORDER BY ev.seq) AS batch FROM combine_jed_status_events ev) t GROUP BY t.batch;
