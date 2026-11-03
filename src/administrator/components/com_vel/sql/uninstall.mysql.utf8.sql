-- Uninstalling com_vel does NOT drop its tables (P1-30).
--
-- An advisory is a published security statement with a CVE behind it, and the reports and
-- correspondence under it are the disclosure record. Both outlive the component being installed.
-- Joomla also uses this file as the rollback step for a failed install, so a failed upgrade would
-- otherwise be enough to lose them.
--
-- The mail templates are genuinely owned by the extension and are recreated on the next install,
-- so they are the only thing removed here.
--
-- To actually drop the tables, use the developer reset: `vendor/bin/robo schema:reset <path>`,
-- gated on `allow_schema_reset = 1` in the [dev] section of jorobo.ini. The statements it runs
-- are in reset.mysql.utf8.sql.

DELETE FROM `#__mail_templates` WHERE `extension` = 'com_vel';
