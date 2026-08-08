-- Developer reset for JED catalogue (com_jed) - P1-30.
--
-- These statements used to sit at the top of install.mysql.utf8.sql, one DROP before every
-- CREATE. That made a reinstall a total data loss, so they were moved here, out of the
-- manifest: this file is never referenced by jed.xml and Joomla therefore never
-- runs it. The only thing that runs it is `vendor/bin/robo schema:reset <joomla-path>`, and
-- that command refuses to do anything unless `allow_schema_reset = 1` is set in the [dev]
-- section of jorobo.ini - a file that is not in the repository and not reachable from the web
-- UI. Off by default, on only where somebody has deliberately turned it on.
--
-- The reset workflow itself is unchanged and still worth having: drop everything, let
-- install.mysql.utf8.sql rebuild it, re-run the import. That is what schema:reset does, in
-- that order.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_extensions`;
DROP TABLE IF EXISTS `#__jed_extensions_history`;
DROP TABLE IF EXISTS `#__jed_extensions_category_map`;
DROP TABLE IF EXISTS `#__jed_extensions_maintainers`;
DROP TABLE IF EXISTS `#__jed_extensions_images`;
DROP TABLE IF EXISTS `#__jed_extensions_files`;
DROP TABLE IF EXISTS `#__jed_reviews`;
DROP TABLE IF EXISTS `#__jed_favorites`;
DROP TABLE IF EXISTS `#__jed_user_access`;
DROP TABLE IF EXISTS `#__jed_user_review_bans`;
DROP TABLE IF EXISTS `#__jed_suspect_ip_ranges`;
DROP TABLE IF EXISTS `#__jed_extension_transfers`;
DROP TABLE IF EXISTS `#__jed_transfer_lookups`;
DROP TABLE IF EXISTS `#__jed_block_reasons`;
DROP TABLE IF EXISTS `#__jed_joomla_versions`;
DROP TABLE IF EXISTS `#__jed_queue_jobs`;
DROP TABLE IF EXISTS `#__jed_url_checks`;
DROP TABLE IF EXISTS `#__jed_extension_linkchecks`;
DROP TABLE IF EXISTS `#__jed_hit_log`;
DROP TABLE IF EXISTS `#__jed_hit_stats`;

SET FOREIGN_KEY_CHECKS = 1;
