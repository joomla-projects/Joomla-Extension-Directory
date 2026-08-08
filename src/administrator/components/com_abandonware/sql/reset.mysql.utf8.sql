-- Developer reset for JED abandonware directory (com_abandonware) - P1-30.
--
-- These statements used to sit at the top of install.mysql.utf8.sql, one DROP before every
-- CREATE. That made a reinstall a total data loss, so they were moved here, out of the
-- manifest: this file is never referenced by abandonware.xml and Joomla therefore never
-- runs it. The only thing that runs it is `vendor/bin/robo schema:reset <joomla-path>`, and
-- that command refuses to do anything unless `allow_schema_reset = 1` is set in the [dev]
-- section of jorobo.ini - a file that is not in the repository and not reachable from the web
-- UI. Off by default, on only where somebody has deliberately turned it on.
--
-- The reset workflow itself is unchanged and still worth having: drop everything, let
-- install.mysql.utf8.sql rebuild it, re-run the import. That is what schema:reset does, in
-- that order.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_abandonware_reports`;
DROP TABLE IF EXISTS `#__jed_abandonware_cases`;

SET FOREIGN_KEY_CHECKS = 1;
