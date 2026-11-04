-- Developer reset for the Vulnerable Extension List (com_vel) - P1-30.
--
-- Never referenced by vel.xml and therefore never run by Joomla. The only thing that runs it is
-- `vendor/bin/robo schema:reset <joomla-path>`, which refuses unless `allow_schema_reset = 1` is
-- set in the [dev] section of jorobo.ini. Off by default, on only where somebody has deliberately
-- turned it on.

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__vel_feed_items`;
DROP TABLE IF EXISTS `#__vel_feed_runs`;
DROP TABLE IF EXISTS `#__vel_attachments`;
DROP TABLE IF EXISTS `#__vel_messages`;
DROP TABLE IF EXISTS `#__vel_participants`;
DROP TABLE IF EXISTS `#__vel_reports`;
DROP TABLE IF EXISTS `#__vel_advisory_history`;
DROP TABLE IF EXISTS `#__vel_advisory_credits`;
DROP TABLE IF EXISTS `#__vel_advisory_references`;
DROP TABLE IF EXISTS `#__vel_cwe`;
DROP TABLE IF EXISTS `#__vel_advisory_cwes`;
DROP TABLE IF EXISTS `#__vel_advisory_versions`;
DROP TABLE IF EXISTS `#__vel_advisory_products`;
DROP TABLE IF EXISTS `#__vel_advisories`;

SET FOREIGN_KEY_CHECKS = 1;
