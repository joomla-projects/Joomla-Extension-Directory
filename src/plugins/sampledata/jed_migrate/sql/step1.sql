-- Disable foreign key checks and drop leftover staging tables from an earlier run
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS combine_jed_extensions;
DROP TABLE IF EXISTS combine_jed_review_texts;
