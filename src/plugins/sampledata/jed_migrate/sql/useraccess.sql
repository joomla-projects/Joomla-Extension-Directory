-- Per-user privileges, bans and the suspect IP ranges (P1-05), from wqyh6_jed_user_access and its
-- three helper tables.
--
-- Cleared first, in dependency order, so a re-run cannot leave a privilege or a ban the source no
-- longer has. These are permission records: a stale row here silently grants or withholds
-- something, which is worse than an absent one.
DELETE FROM #__jed_user_review_bans;

DELETE FROM #__jed_user_access;

DELETE FROM #__jed_suspect_ip_ranges;

-- The privilege columns line up one to one. The two that do not:
--
--   set_by has no source. JED3 records *when* an access row was changed (modified_time) but never
--   by whom. NULL rather than a guess; the value is only ever shown as "set by".
--
--   banned_from / banned_until. This is the one mapping that cannot be taken at face value, and
--   getting it wrong is not a cosmetic loss: P1-05 compares banned_until against now, so a ban
--   with an end date in the past stops applying by itself. Import the wrong end date and every
--   ban in the catalogue is silently lifted on the day of the switch.
--
--   JED3 carries both a period (apply_period: '-1', '30', '60', '90', 'other') and an end date
--   (apply_till_time), and the end date is **not maintained**: on 191 of the 202 rows it holds the
--   *start* time to the second, including on every single '30' / '60' / '90' row. Only the three
--   'other' rows carry a genuine end. So the period is the carrier and apply_till_time is only
--   read where the period says it was set by hand:
--
--     '30' / '60' / '90'  ->  start + N days. Long past on this stock, which is correct - they
--                             were thirty-day bans, and they ended.
--     'other'             ->  apply_till_time, and only when it is actually after the start.
--     '-1', '' and NULL   ->  NULL, meaning no end. '-1' is JED3's "permanent"; the empty period
--                             recorded no end at all and its reasons read "Spammer", "Duplicate
--                             account", "Multiple accounts setup to post negative reviews", so
--                             treating it as open-ended is what the row says.
--
-- JED3 has 202 access rows for 163 distinct users - the same user was given a second row rather
-- than the first being edited. The new table is keyed on user_id, so the most recent row wins;
-- MAX(id) rather than MAX(modified_time) because the timestamps tie and the id does not.
--
-- Rows for user 0 are dropped: two of them exist and belong to no account.
INSERT INTO #__jed_user_access (user_id, create_listing, edit_listing, update_xml, review, report, auto_approve_extensions, auto_approve_reviews, banned, banned_reason, banned_from, banned_until, set_by, set_time) SELECT a.user_id, IFNULL(a.create_listing, 1), IFNULL(a.edit_listing, 1), IFNULL(a.update_xml, 1), IFNULL(a.review, 1), IFNULL(a.report, 1), IFNULL(a.auto_approve_extensions, 0), IFNULL(a.auto_approve_reviews, 0), IFNULL(a.banned, 0), NULLIF(TRIM(IFNULL(a.banned_reason, '')), ''), NULLIF(a.apply_start_time, '0000-00-00 00:00:00'), CASE WHEN a.apply_period REGEXP '^[0-9]+$' AND CAST(a.apply_period AS UNSIGNED) > 0 AND NULLIF(a.apply_start_time, '0000-00-00 00:00:00') IS NOT NULL THEN DATE_ADD(a.apply_start_time, INTERVAL CAST(a.apply_period AS UNSIGNED) DAY) WHEN a.apply_period = 'other' AND NULLIF(a.apply_till_time, '0000-00-00 00:00:00') > NULLIF(a.apply_start_time, '0000-00-00 00:00:00') THEN a.apply_till_time END, NULL, NULLIF(a.modified_time, '0000-00-00 00:00:00') FROM wqyh6_jed_user_access a INNER JOIN #__users u ON u.id = a.user_id WHERE a.user_id <> 0 AND a.id = (SELECT MAX(a2.id) FROM wqyh6_jed_user_access a2 WHERE a2.user_id = a.user_id);

-- "Barred from reviewing this particular developer", not barred from reviewing at all. JED3 keeps
-- it in wqyh6_jed_user_access_banned_review_developers: acces_id points at the access row, whose
-- user_id is the *reviewer* being restricted, and the table's own user_id is the *developer* they
-- may not review. Both directions are checked here, which is why both joins onto #__users exist.
--
-- The table has 205 rows and 9 real ones. The other 196 are placeholders written alongside every
-- access row with target 0, which is not a user id - the same "0 means unset" habit the linked
-- extensions had. They are dropped rather than imported as bans against user 0.
--
-- The ban is attached to the reviewer, not to the access row, so a ban whose access row lost the
-- MAX(id) contest above still lands. That is also why this is INSERT IGNORE: one reviewer holds
-- the same developer ban through two access rows, and (user_id, target_type, target_id) is the
-- primary key, so the second copy collapses into the first. Six source rows, five bans.
INSERT IGNORE INTO #__jed_user_review_bans (user_id, target_type, target_id, set_by, set_time) SELECT ua.user_id, 'developer', bd.user_id, NULL, NULLIF(ua.modified_time, '0000-00-00 00:00:00') FROM wqyh6_jed_user_access_banned_review_developers bd INNER JOIN wqyh6_jed_user_access ua ON ua.id = bd.acces_id INNER JOIN #__users u ON u.id = ua.user_id INNER JOIN #__users d ON d.id = bd.user_id WHERE ua.user_id <> 0 AND bd.user_id <> 0;

-- The same table for categories. wqyh6_jed_user_access_banned_categories holds 205 rows and, on
-- this stock, not one of them has a category: every single row is the 0 placeholder. The statement
-- is here regardless - the JED3 feature exists, the data set simply happens to be empty, and a
-- migration that only works on the data it was written against is not a migration.
INSERT IGNORE INTO #__jed_user_review_bans (user_id, target_type, target_id, set_by, set_time) SELECT ua.user_id, 'category', bc.category_id, NULL, NULLIF(ua.modified_time, '0000-00-00 00:00:00') FROM wqyh6_jed_user_access_banned_categories bc INNER JOIN wqyh6_jed_user_access ua ON ua.id = bc.acces_id INNER JOIN #__users u ON u.id = ua.user_id INNER JOIN #__categories c ON c.id = bc.category_id AND c.extension = 'com_jed' WHERE ua.user_id <> 0 AND bc.category_id <> 0;

-- The suspect IP ranges (P1-05 item 8). Advisory only: a match flags, it never blocks.
--
-- The new columns are varbinary(16) through INET6_ATON, which takes IPv4 and IPv6 alike - the
-- source has 16 of the first and one of the second, as text. Six of the 17 rows have an empty
-- "end", meaning a single address rather than a range, so the end falls back to the start; a range
-- ending at NULL would match nothing and the row would be there without doing anything.
--
-- Anything INET6_ATON cannot parse is skipped rather than stored as NULL, for the same reason.
--
-- The note carries the provenance, because the new table has no created/created_by and the only
-- thing that makes an advisory range reviewable later is knowing who added it and when.
INSERT INTO #__jed_suspect_ip_ranges (range_start, range_end, note, state) SELECT INET6_ATON(TRIM(r.start)), INET6_ATON(IFNULL(NULLIF(TRIM(r.end), ''), TRIM(r.start))), LEFT(CONCAT('JED3 range ', r.id, ', added ', IFNULL(DATE_FORMAT(NULLIF(r.created, '0000-00-00 00:00:00'), '%Y-%m-%d'), 'date unknown'), ' by user ', IFNULL(NULLIF(r.created_by, 0), 'unknown')), 255), 1 FROM wqyh6_jed_suspect_ip_range r WHERE INET6_ATON(TRIM(r.start)) IS NOT NULL AND INET6_ATON(IFNULL(NULLIF(TRIM(r.end), ''), TRIM(r.start))) IS NOT NULL;
