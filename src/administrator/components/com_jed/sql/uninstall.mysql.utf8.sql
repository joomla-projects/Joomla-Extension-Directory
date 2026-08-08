-- Uninstalling com_jed does NOT drop the catalogue (P1-30).
--
-- For an ordinary component, dropping its tables on uninstall is correct. For this one it is
-- catastrophic: the tables below are the Joomla Extension Directory. Two clicks in the backend -
-- or one failed install, because Joomla pushes this file as the rollback step for a failed
-- install (InstallerAdapter::doDatabaseTransactions) - would end fifteen years of listings,
-- reviews and moderation history.
--
-- So this file removes only what is genuinely owned by the extension and is recreated on the next
-- install: the mail templates. The data tables are left standing. A reinstall finds them and
-- adopts them; install.mysql.utf8.sql is written to make that work.
--
-- To actually drop the tables, use the developer reset: `vendor/bin/robo schema:reset <path>`,
-- gated on `allow_schema_reset = 1` in the [dev] section of jorobo.ini. That is a deliberate act
-- on a development machine, not something reachable from a backend anyone on the JED team can
-- get to. The statements it runs are in reset.mysql.utf8.sql.

DELETE FROM `#__mail_templates` WHERE `template_id` IN ('com_jed.audit_report', 'com_jed.link_broken');
