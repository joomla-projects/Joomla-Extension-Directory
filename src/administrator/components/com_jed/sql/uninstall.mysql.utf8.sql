SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_extensions_category_map`;
DROP TABLE IF EXISTS `#__jed_extensions_maintainers`;
DROP TABLE IF EXISTS `#__jed_extensions_images`;
DROP TABLE IF EXISTS `#__jed_extensions_files`;
DROP TABLE IF EXISTS `#__jed_reviews`;
DROP TABLE IF EXISTS `#__jed_favorites`;
DROP TABLE IF EXISTS `#__jed_extensions`;
DROP TABLE IF EXISTS `#__jed_extensions_history`;
DROP TABLE IF EXISTS `#__jed_joomla_versions`;
DROP TABLE IF EXISTS `#__jed_block_reasons`;
DROP TABLE IF EXISTS `#__jed_extension_transfers`;
DROP TABLE IF EXISTS `#__jed_transfer_lookups`;
DROP TABLE IF EXISTS `#__jed_user_access`;
DROP TABLE IF EXISTS `#__jed_user_review_bans`;
DROP TABLE IF EXISTS `#__jed_suspect_ip_ranges`;
DROP TABLE IF EXISTS `#__jed_queue_jobs`;
DROP TABLE IF EXISTS `#__jed_url_checks`;
DROP TABLE IF EXISTS `#__jed_extension_linkchecks`;
DROP TABLE IF EXISTS `#__jed_hit_log`;
DROP TABLE IF EXISTS `#__jed_hit_stats`;

DELETE FROM `#__mail_templates` WHERE `template_id` IN ('com_jed.audit_report', 'com_jed.link_broken');

SET FOREIGN_KEY_CHECKS = 1;
