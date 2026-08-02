-- Drop the staging tables and re-enable foreign key checks. combine_jed_extensions is kept: it still holds the
-- JED3 columns that have no target column yet (tags, price, currency, language_code, parent_id,
-- related_free_paid_id), which the tag and linked-extension migrations will need.
DROP TABLE IF EXISTS combine_jed_review_texts;
DROP TABLE IF EXISTS combine_jed_history_map;
DROP TABLE IF EXISTS combine_jed_history_cfg;
DROP TABLE IF EXISTS combine_jed_history_done;
DROP TABLE IF EXISTS combine_jed_history_batches;
SET FOREIGN_KEY_CHECKS = 1;
