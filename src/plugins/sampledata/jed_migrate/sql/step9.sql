-- Copy the watchlist from wqyh6_jed_extensions_favoured. Rows whose user or extension did not survive the
-- migration are dropped by the joins.
DELETE FROM #__jed_favorites;
INSERT IGNORE INTO #__jed_favorites (user_id, extension_id, created) SELECT f.user_id, f.extension_id, IFNULL(NULLIF(f.created, '0000-00-00 00:00:00'), '2000-01-01 00:00:00') FROM wqyh6_jed_extensions_favoured f INNER JOIN #__jed_extensions e ON e.id = f.extension_id INNER JOIN #__users u ON u.id = f.user_id;
