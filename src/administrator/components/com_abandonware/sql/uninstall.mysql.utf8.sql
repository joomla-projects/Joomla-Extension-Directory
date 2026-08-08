-- Uninstalling com_abandonware does NOT drop its tables (P1-30).
--
-- A case is a process the JED team ran against a real developer, with the reasons recorded. It is
-- the evidence for having taken a listing off the directory, and it outlives the component being
-- installed. Joomla also uses this file as the rollback step for a failed install, so a failed
-- upgrade would otherwise be enough to lose it.
--
-- The mail template is genuinely owned by the extension and is recreated on the next install, so
-- it is the only thing removed here.
--
-- To actually drop the tables, use the developer reset: `vendor/bin/robo schema:reset <path>`,
-- gated on `allow_schema_reset = 1` in the [dev] section of jorobo.ini. The statements it runs
-- are in reset.mysql.utf8.sql.

DELETE FROM `#__mail_templates` WHERE `template_id` = 'com_abandonware.owner_contact';
