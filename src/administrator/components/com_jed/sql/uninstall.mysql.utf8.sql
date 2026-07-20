SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_extensions_category_map`;
DROP TABLE IF EXISTS `#__jed_extensions_maintainers`;
DROP TABLE IF EXISTS `#__jed_extensions_images`;
DROP TABLE IF EXISTS `#__jed_extensions_files`;
DROP TABLE IF EXISTS `#__jed_reviews`;
DROP TABLE IF EXISTS `#__jed_reviews_comments`;
DROP TABLE IF EXISTS `#__jed_extensions`;
DROP TABLE IF EXISTS `#__jed_extension_images`;
DROP TABLE IF EXISTS `#__jed_extension_scores`;
DROP TABLE IF EXISTS `#__jed_developers`;
DROP TABLE IF EXISTS `#__jed_extensions_history`;
DROP TABLE IF EXISTS `#__jed_joomla_versions`;
DROP TABLE IF EXISTS `#__jed_extensions_history`;
DROP TABLE IF EXISTS `#__jed_queue_jobs`;

DELETE FROM `#__mail_templates` WHERE `template_id` = 'com_jed.audit_report';

SET FOREIGN_KEY_CHECKS = 1;
