SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `#__jed_abandonware_reports`;
DROP TABLE IF EXISTS `#__jed_abandonware_cases`;

DELETE FROM `#__mail_templates` WHERE `template_id` = 'com_abandonware.owner_contact';

SET FOREIGN_KEY_CHECKS = 1;
