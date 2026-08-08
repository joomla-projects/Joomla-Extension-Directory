-- P1-16 - the tag assignments. Third and last of the tag steps; runs once, after every
-- tags_ucm_batch.sql slice has been applied.

-- #__ucm_base mirrors the rows the batches wrote. ucm_id IS the core_content_id - that identity is
-- what CoreContent::storeUcmBase() writes, and TagsHelper::postStore() relies on it: it looks an
-- item's UCM row up by (ucm_item_id, ucm_type_id) here. If the row is missing, the first save of an
-- already-imported extension creates a SECOND #__ucm_content row and the imported assignments are
-- orphaned from that moment on. ucm_language_id is 0 for "all languages", which is what
-- ContentHelper::getLanguageId() returns for '*'.
INSERT INTO #__ucm_base (ucm_id, ucm_item_id, ucm_type_id, ucm_language_id)
SELECT c.core_content_id, c.core_content_item_id, c.core_type_id, 0
FROM #__ucm_content c
WHERE c.core_type_alias = 'com_jed.extension';

-- Staging table, for one reason: core does not index #__ucm_content.core_content_item_id, and the
-- assignment insert below has to look a UCM row up by exactly that for each of 38,677 rows. Joined
-- straight against #__ucm_content the statement takes 27 seconds against the real data set; through
-- this table it is under a second. Adding the index to #__ucm_content would be the other fix, but
-- that table belongs to core and is shared with every other component - a migration plugin has no
-- business changing its schema.
DROP TABLE IF EXISTS combine_jed_tag_ucm;

CREATE TABLE combine_jed_tag_ucm (
  content_item_id INT NOT NULL PRIMARY KEY,
  core_content_id INT UNSIGNED NOT NULL,
  core_type_id INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

INSERT INTO combine_jed_tag_ucm (content_item_id, core_content_id, core_type_id)
SELECT c.core_content_item_id, c.core_content_id, c.core_type_id
FROM #__ucm_content c
WHERE c.core_type_alias = 'com_jed.extension';

-- The two INNER JOINs are the filter: an assignment survives only if both its extension and its tag
-- made it into the new system. That drops the 6,342 assignments (13.5 %) whose extension no longer
-- exists and the 2,005 whose tag was not imported - both counted on the step's report rather than
-- silently discarded.
--
-- tag_date is taken from the legacy row. The column is ON UPDATE CURRENT_TIMESTAMP, so it must be
-- written explicitly; left to the default, the entire vocabulary would claim to have been tagged on
-- the day the migration ran.
INSERT INTO #__contentitem_tag_map (core_content_id, content_item_id, tag_id, type_id, type_alias, tag_date)
SELECT u.core_content_id, m.content_item_id, m.tag_id, u.core_type_id, 'com_jed.extension',
       IFNULL(NULLIF(m.tag_date, '0000-00-00 00:00:00'), '2000-01-01 00:00:00')
FROM wqyh6_contentitem_tag_map m
INNER JOIN #__tags t ON t.id = m.tag_id
INNER JOIN combine_jed_tag_ucm u ON u.content_item_id = m.content_item_id
WHERE m.type_alias = 'com_jed.extension';
