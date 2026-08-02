-- Copy the reviews. Every JED3 review row carries the five criterion scores, and wqyh6_jed_reviews_users says
-- which account wrote it - that mapping is used for created_by, so each legacy score ends up as a review
-- belonging to the right user. Where the cross reference is missing the UCM author is used instead.
-- overall_score is the average of the criteria that were actually scored. The previous version of this script
-- summed them, which both misrepresented the rating and overflowed the decimal(2,1) column as soon as more than
-- two criteria were filled in. The synthetic "migrated extension score" review the old script inserted per
-- extension is gone: the aggregate already lives on #__jed_extensions.score_*, and adding it as a review
-- attributed the developer a review they never wrote and inflated score_count.
DELETE FROM #__jed_reviews;
INSERT INTO #__jed_reviews (extension_id, title, alias, body, functionality, functionality_comment, ease_of_use, ease_of_use_comment, support, support_comment, documentation, documentation_comment, value_for_money, value_for_money_comment, overall_score, used_for, version, flagged, ip_address, state, created_on, created_by, ordering) SELECT r.extension_id, LEFT(IFNULL(u.core_title, ''), 400), LEFT(IFNULL(u.core_alias, ''), 400), u.core_body, LEAST(GREATEST(IFNULL(r.functionality, 0), 0), 9.9), '', LEAST(GREATEST(IFNULL(r.ease_of_use, 0), 0), 9.9), '', LEAST(GREATEST(IFNULL(r.support, 0), 0), 9.9), '', LEAST(GREATEST(IFNULL(r.documentation, 0), 0), 9.9), '', LEAST(GREATEST(IFNULL(r.value_for_money, 0), 0), 9.9), '', LEAST(GREATEST(IFNULL(ROUND((IFNULL(r.functionality, 0) + IFNULL(r.ease_of_use, 0) + IFNULL(r.support, 0) + IFNULL(r.documentation, 0) + IFNULL(r.value_for_money, 0)) / NULLIF((r.functionality IS NOT NULL) + (r.ease_of_use IS NOT NULL) + (r.support IS NOT NULL) + (r.documentation IS NOT NULL) + (r.value_for_money IS NOT NULL), 0), 1), 0), 0), 9.9), LEFT(IFNULL(r.used_for, ''), 400), LEFT(IFNULL(r.version, ''), 255), CAST(IFNULL(r.flagged, 0) AS CHAR), LEFT(IFNULL(r.ip_address, ''), 255), CASE WHEN u.core_state = 1 THEN 1 ELSE 0 END, NULLIF(u.core_created_time, '0000-00-00 00:00:00'), IFNULL(ru.user_id, u.core_created_user_id), 0 FROM wqyh6_jed_reviews r INNER JOIN wqyh6_ucm_content u ON u.core_content_item_id = r.id AND u.core_type_alias = 'com_jed.review' INNER JOIN #__jed_extensions e ON e.id = r.extension_id LEFT JOIN (SELECT review_id, MIN(user_id) AS user_id FROM wqyh6_jed_reviews_users GROUP BY review_id) ru ON ru.review_id = r.id ORDER BY r.extension_id ASC, u.core_created_time ASC;

-- Extract the per-criterion comments that JED3 stored inline in the review body as
-- {functionality}...{/functionality} markers
CREATE TABLE combine_jed_review_texts (
  id INT UNSIGNED NOT NULL, functionality_comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, ease_of_use_comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, support_comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, documentation_comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, value_for_money_comment TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, body MEDIUMTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci, PRIMARY KEY (id)
) ENGINE = INNODB DEFAULT CHARSET = utf8mb4;
INSERT INTO combine_jed_review_texts (id, functionality_comment, ease_of_use_comment, support_comment, documentation_comment, value_for_money_comment, body) SELECT id, '', '', '', '', '', REPLACE(REPLACE(body, '\r', ''), '\n', '') FROM #__jed_reviews WHERE body LIKE '%{functionality}%' OR body LIKE '%{ease_of_use}%' OR body LIKE '%{support}%' OR body LIKE '%{documentation}%' OR body LIKE '%{value_for_money}%';
UPDATE combine_jed_review_texts SET functionality_comment = REGEXP_SUBSTR(body, '[{]functionality[}].*[{]/functionality[}]');
UPDATE combine_jed_review_texts SET ease_of_use_comment = REGEXP_SUBSTR(body, '[{]ease_of_use[}].*[{]/ease_of_use[}]');
UPDATE combine_jed_review_texts SET support_comment = REGEXP_SUBSTR(body, '[{]support[}].*[{]/support[}]');
UPDATE combine_jed_review_texts SET documentation_comment = REGEXP_SUBSTR(body, '[{]documentation[}].*[{]/documentation[}]');
UPDATE combine_jed_review_texts SET value_for_money_comment = REGEXP_SUBSTR(body, '[{]value_for_money[}].*[{]/value_for_money[}]');
UPDATE combine_jed_review_texts SET functionality_comment = REPLACE(REPLACE(IFNULL(functionality_comment, ''), '{functionality}', ''), '{/functionality}', '');
UPDATE combine_jed_review_texts SET ease_of_use_comment = REPLACE(REPLACE(IFNULL(ease_of_use_comment, ''), '{ease_of_use}', ''), '{/ease_of_use}', '');
UPDATE combine_jed_review_texts SET support_comment = REPLACE(REPLACE(IFNULL(support_comment, ''), '{support}', ''), '{/support}', '');
UPDATE combine_jed_review_texts SET documentation_comment = REPLACE(REPLACE(IFNULL(documentation_comment, ''), '{documentation}', ''), '{/documentation}', '');
UPDATE combine_jed_review_texts SET value_for_money_comment = REPLACE(REPLACE(IFNULL(value_for_money_comment, ''), '{value_for_money}', ''), '{/value_for_money}', '');
UPDATE #__jed_reviews njr INNER JOIN combine_jed_review_texts cjt ON njr.id = cjt.id SET njr.functionality_comment = cjt.functionality_comment, njr.ease_of_use_comment = cjt.ease_of_use_comment, njr.support_comment = cjt.support_comment, njr.documentation_comment = cjt.documentation_comment, njr.value_for_money_comment = cjt.value_for_money_comment;
UPDATE #__jed_reviews SET body = 'Old Review System - No overall field' WHERE body LIKE '%{functionality}%';

-- Recompute score_count from the reviews that were actually imported, instead of trusting the JED3 num_reviews
-- counter
UPDATE #__jed_extensions e SET e.score_count = (SELECT COUNT(*) FROM #__jed_reviews r WHERE r.extension_id = e.id);
