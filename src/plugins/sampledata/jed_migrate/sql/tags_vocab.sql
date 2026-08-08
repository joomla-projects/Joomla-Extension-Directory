-- P1-16 - import the legacy tag vocabulary. First of three tag steps: this one clears what an
-- earlier run wrote and creates the #__tags records; tags_ucm_batch.sql fills #__ucm_content in
-- slices; tags_map.sql writes the assignments.
--
-- The split exists because the #__ucm_content insert alone takes ~28 seconds against the real data
-- set - 17 MB of listing descriptions across 13,860 rows into a core table carrying twelve
-- secondary indexes. That is the same reason the history import is spread over batches: one PHP
-- timeout and the run is lost. All three steps here are individually well under a second except
-- the batches, which are ~5 seconds each.
--
-- There is only ONE source. 7.4/7.5 assumed two, but the free-text column wqyh6_jed_extensions.tags
-- is empty in every one of the 14,914 rows, so core tags (wqyh6_contentitem_tag_map) are the whole
-- of it and the "which source is authoritative" question from P0-03 has no second candidate.
--
-- Three tables have to be written, not one. A tag listing page is built by
-- TagsHelper::getTagItemsQuery(), which INNER JOINs #__contentitem_tag_map to #__ucm_content on
-- (type_alias, core_content_id) and to #__content_types on type_alias. Import only the map and
-- every /tags/<slug> page renders empty. #__ucm_base matters too, but for a different reason: after
-- the import, TagsHelper::postStore() looks an item's UCM row up by (ucm_item_id, ucm_type_id) in
-- #__ucm_base - if it is missing, the first save of an already-imported extension creates a SECOND
-- #__ucm_content row and the imported assignments are orphaned from that moment on.
--
-- The content type row itself is written by this step's PHP "before" hook (ContentTypeHelper), and
-- the tag nested set is repaired by its "after" hook - see jed_migrate.php. The deletes below run
-- here rather than in the later steps so that a re-run is clean from its first statement: after
-- this step the target holds the new vocabulary and no assignments at all.
--
-- Repeatable (P1-24): every insert below is preceded by the delete that removes what the previous
-- run wrote, and imported tags keep their JED3 ids, so the source table is an exact description of
-- the rows this step owns. Tags created later in the new system get ids from AUTO_INCREMENT above
-- the legacy range and are never touched.

-- ---------------------------------------------------------------------------------------------
-- 1. Undo the previous run.
-- ---------------------------------------------------------------------------------------------

DELETE FROM #__contentitem_tag_map WHERE type_alias = 'com_jed.extension';

DELETE b FROM #__ucm_base b
  INNER JOIN #__content_types ct ON ct.type_id = b.ucm_type_id
  WHERE ct.type_alias = 'com_jed.extension';

DELETE FROM #__ucm_content WHERE core_type_alias = 'com_jed.extension';

DELETE FROM #__tags WHERE id > 1 AND id IN (SELECT id FROM wqyh6_tags WHERE id > 1);

-- ---------------------------------------------------------------------------------------------
-- 2. The tag records.
-- ---------------------------------------------------------------------------------------------
--
-- Two filters, and no others. Both are stated as counts on the run's report so that what was left
-- out is visible rather than inferred.
--
--  * At least two surviving assignments. "A tag used once is noise" is the plan's wording; a tag
--    used zero times is not a vocabulary entry at all. This also disposes of the entire
--    normalisation problem in the legacy data: all four duplicate titles (AMP, GDPR, Custom Fields,
--    Phoca Cart extensions x4) are alias-less rows with parent_id = 0 and no assignments, i.e.
--    accidents of the JED3 admin UI, and each has exactly one real counterpart that survives. There
--    are no case variants, plurals or near-duplicates to merge - this is a curated core-tags
--    vocabulary, not the free-text field 7.5 expected.
--  * A non-empty alias. Without one there is no /tags/<slug> to serve, and core would route the
--    tag by id instead. Every row this excludes is already excluded by the threshold.
--
-- What is deliberately NOT filtered here is the 314 tags whose title duplicates a com_jed category
-- title. P0-03 recommended dropping them, on the reasoning that they clone the category tree. That
-- recommendation was made without the published state: those 314 are ALL published in JED3 and
-- serve live /tags/<slug> pages today, while 197 of the 207 tags that are NOT category titles are
-- unpublished. Dropping the category-titled tags would therefore delete almost the entire *visible*
-- tag vocabulary of the live site - the opposite of what the recommendation intended.
--
-- So the legacy `published` state is carried over verbatim. The new site then behaves for each tag
-- exactly as the old one does, and the curation decision - which is the JED team's to make, not
-- this import's - is made in the admin against imported records rather than by editing an SQL file
-- and re-running a migration. The step's report names both groups.
--
-- One thing the report has to say out loud, because it is counter-intuitive and it is what the
-- curation decision actually turns on: in core com_tags an unpublished tag is NOT a hidden page.
-- TagModel::getItem() filters on the tag's published state, but getListQuery() passes that state
-- through as the filter on the *items*, not on the tag - so /tags/<slug> of an unpublished tag
-- answers 200 and lists its extensions, withholding only the tag's own title and description.
-- Verified against the test site. Leaving a tag unpublished therefore does not take it off the web;
-- only deleting the record does.

INSERT INTO #__tags (
  id, parent_id, lft, rgt, level, path, title, alias, note, description, published,
  checked_out, checked_out_time, access, params, metadesc, metakey, metadata,
  created_user_id, created_time, created_by_alias, modified_user_id, modified_time,
  images, urls, hits, language, version, publish_up, publish_down
)
SELECT
  t.id,
  -- Every legacy tag is a direct child of ROOT. Six rows claim parent_id = 0, which is not a node:
  -- they are the alias-less duplicates, and the threshold drops them anyway. Forcing 1 here keeps
  -- the rebuild in the "after" hook from meeting an unreachable subtree even if that ever changes.
  1,
  0, 0, 1,
  LEFT(t.alias, 400),
  LEFT(t.title, 255),
  LEFT(t.alias, 400),
  LEFT(IFNULL(t.note, ''), 255),
  IFNULL(t.description, ''),
  t.published,
  NULL,
  NULLIF(t.checked_out_time, '0000-00-00 00:00:00'),
  t.access,
  IFNULL(t.params, ''),
  LEFT(IFNULL(t.metadesc, ''), 1024),
  LEFT(IFNULL(t.metakey, ''), 1024),
  LEFT(IFNULL(t.metadata, ''), 2048),
  t.created_user_id,
  IFNULL(NULLIF(t.created_time, '0000-00-00 00:00:00'), '2000-01-01 00:00:00'),
  LEFT(IFNULL(t.created_by_alias, ''), 255),
  t.modified_user_id,
  IFNULL(NULLIF(t.modified_time, '0000-00-00 00:00:00'), '2000-01-01 00:00:00'),
  IFNULL(t.images, ''),
  IFNULL(t.urls, ''),
  t.hits,
  IFNULL(NULLIF(t.language, ''), '*'),
  t.version,
  NULLIF(t.publish_up, '0000-00-00 00:00:00'),
  NULLIF(t.publish_down, '0000-00-00 00:00:00')
FROM wqyh6_tags t
WHERE t.id > 1
  AND TRIM(IFNULL(t.alias, '')) <> ''
  AND (
    SELECT COUNT(*)
    FROM wqyh6_contentitem_tag_map m
    INNER JOIN #__jed_extensions e ON e.id = m.content_item_id
    WHERE m.tag_id = t.id AND m.type_alias = 'com_jed.extension'
  ) >= {{TAG_MIN_USES}};

