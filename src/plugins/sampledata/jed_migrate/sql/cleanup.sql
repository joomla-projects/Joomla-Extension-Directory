-- Drop the staging tables and re-enable foreign key checks. combine_jed_extensions is kept: it still holds the
-- JED3 columns that have no target column yet (price, currency, language_code, parent_id,
-- related_free_paid_id), which the linked-extension migration will need. Its `tags` column is not
-- among them - P1-16 established that the JED3 free-text tag field is empty in every row and that
-- core tags are the only source, so the tag import (tags.sql) does not read this table at all.
DROP TABLE IF EXISTS combine_jed_review_texts;
DROP TABLE IF EXISTS combine_jed_tag_ucm;
DROP TABLE IF EXISTS combine_jed_history_map;
DROP TABLE IF EXISTS combine_jed_history_cfg;
DROP TABLE IF EXISTS combine_jed_history_done;
DROP TABLE IF EXISTS combine_jed_history_batches;
SET FOREIGN_KEY_CHECKS = 1;
