-- Remember which local super user to keep, so the migration does not lock the current administrator out. The
-- lowest-numbered member of the "Super Users" group is retained.
SET @jed_super_group = (SELECT id FROM #__usergroups WHERE title = 'Super Users' ORDER BY id ASC LIMIT 1);
SET @jed_keep_user = (SELECT MIN(user_id) FROM #__user_usergroup_map WHERE group_id = @jed_super_group);

-- Clear local users and group assignments, except the retained administrator
DELETE FROM #__user_usergroup_map WHERE user_id != IFNULL(@jed_keep_user, 0);
DELETE FROM #__users WHERE id != IFNULL(@jed_keep_user, 0);

-- Replace the user groups with the JED3 tree. Both sides are Joomla installations, so the standard groups keep
-- their ids and the JED-specific groups come across with theirs - which is what makes the group map below
-- meaningful.
DELETE FROM #__usergroups;
INSERT INTO #__usergroups (id, parent_id, lft, rgt, title) SELECT id, parent_id, lft, rgt, title FROM wqyh6_usergroups;

-- Copy the users. Zero dates are converted because Joomla 6 declares registerDate NOT NULL and lastvisitDate /
-- lastResetTime nullable. INSERT IGNORE skips any row whose id or username collides with the retained
-- administrator - normally exactly one row.
INSERT IGNORE INTO #__users (id, name, username, email, password, block, sendEmail, registerDate, lastvisitDate, activation, params, lastResetTime, resetCount, otpKey, otep, requireReset, authProvider) SELECT id, name, username, email, password, block, sendEmail, IFNULL(NULLIF(registerDate, '0000-00-00 00:00:00'), '2000-01-01 00:00:00'), NULLIF(lastvisitDate, '0000-00-00 00:00:00'), activation, params, NULLIF(lastResetTime, '0000-00-00 00:00:00'), resetCount, otpKey, otep, requireReset, authProvider FROM wqyh6_users;

-- Copy the user/group assignments. This replaces the manual usergroup_map step the old script left as a TODO.
-- Rows pointing at a user or group that did not survive the copy are skipped by the joins.
INSERT IGNORE INTO #__user_usergroup_map (user_id, group_id) SELECT m.user_id, m.group_id FROM wqyh6_user_usergroup_map m INNER JOIN #__users u ON u.id = m.user_id INNER JOIN #__usergroups g ON g.id = m.group_id;

-- Make sure the retained administrator is still a Super User after the group tree was replaced
INSERT IGNORE INTO #__user_usergroup_map (user_id, group_id) SELECT @jed_keep_user, g.id FROM #__usergroups g INNER JOIN #__users u ON u.id = @jed_keep_user WHERE g.title = 'Super Users';
