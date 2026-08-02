-- Copy JED3 developer data (wqyh6_jed_users) into the "developer_name" and "suspicious" user custom fields. The
-- fields themselves are created by the component's install script - joining #__fields means the step is skipped
-- silently rather than failing if one of them is missing.
DELETE fv FROM #__fields_values fv INNER JOIN #__fields f ON f.id = fv.field_id WHERE f.context = 'com_users.user' AND f.name IN ('developer_name', 'suspicious');
INSERT INTO #__fields_values (field_id, item_id, value) SELECT f.id, CAST(ju.user_id AS CHAR), ju.developer_name FROM wqyh6_jed_users ju INNER JOIN #__users u ON u.id = ju.user_id INNER JOIN #__fields f ON f.context = 'com_users.user' AND f.name = 'developer_name' WHERE ju.user_id IS NOT NULL AND ju.developer_name IS NOT NULL AND TRIM(ju.developer_name) != '';
INSERT INTO #__fields_values (field_id, item_id, value) SELECT f.id, CAST(ju.user_id AS CHAR), '1' FROM wqyh6_jed_users ju INNER JOIN #__users u ON u.id = ju.user_id INNER JOIN #__fields f ON f.context = 'com_users.user' AND f.name = 'suspicious' WHERE ju.user_id IS NOT NULL AND ju.suspicious = 1;
