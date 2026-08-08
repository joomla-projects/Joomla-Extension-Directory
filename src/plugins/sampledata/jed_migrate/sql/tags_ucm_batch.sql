-- P1-16 - one slice of the #__ucm_content rows the tag listing reads. Second of three tag steps.
--
-- One row per extension that keeps at least one assignment. Writing all 14,890 would be simpler,
-- but #__ucm_content is a generic table shared with every other component and there is no reason
-- for an untagged listing to have a row in it.
--
-- Why this table at all: TagsHelper::getTagItemsQuery() INNER JOINs #__contentitem_tag_map to
-- #__ucm_content on (type_alias, core_content_id). Import only the mapping and every /tags/<slug>
-- page renders empty.
--
-- The slice is MOD(id, batches). It costs a full scan of #__jed_extensions per batch - 14,890 rows,
-- nothing next to the write itself - and it needs no staging table and no assumption about the id
-- range being dense, which after a migration that dropped rows it is not.
--
-- core_state is NOT a copy of #__jed_extensions.state. Public visibility of a listing is
-- ListingAccess::forItem()'s answer - deleted = 0 AND approved = 1 AND blocked = 0 AND state = 1 -
-- and a blocked or never-approved listing must not be offered on a tag page. Note that the content
-- type's field_mappings maps core_state onto `state` alone, so a later save through the Taggable
-- behaviour will overwrite the row with the weaker value; that mapping cannot express a condition
-- and is recorded as a follow-up rather than papered over here.
--
-- core_access is left at 0 to match exactly what CoreContent::store() writes for this type
-- (field_mappings maps core_access to "null"); TagsHelper adds 0 to the authorised view levels, so
-- the rows are readable by everyone, which is what "listed in the public catalogue" means.
--
-- No delete here - tags_vocab.sql emptied the table for this type before the first batch ran.

INSERT INTO #__ucm_content (
  core_type_alias, core_title, core_alias, core_body, core_state, core_access,
  core_created_user_id, core_created_time, core_modified_time, core_language,
  core_publish_up, core_publish_down, core_content_item_id, core_images, core_catid, core_type_id
)
SELECT
  'com_jed.extension',
  LEFT(e.name, 400),
  LEFT(e.alias, 400),
  e.description,
  CASE WHEN e.deleted = 0 AND e.approved = 1 AND e.blocked = 0 AND e.state = 1 THEN 1 ELSE 0 END,
  0,
  IFNULL(e.owner, 0),
  IFNULL(e.created, '2000-01-01 00:00:00'),
  IFNULL(e.modified, IFNULL(e.created, '2000-01-01 00:00:00')),
  '*',
  NULL,
  NULL,
  e.id,
  IFNULL(e.logo, ''),
  IFNULL(e.catid, 0),
  (SELECT type_id FROM #__content_types WHERE type_alias = 'com_jed.extension')
FROM #__jed_extensions e
WHERE MOD(e.id, {{TAG_BATCHES}}) = {{TAG_BATCH}}
AND EXISTS (
  SELECT 1
  FROM wqyh6_contentitem_tag_map m
  INNER JOIN #__tags t ON t.id = m.tag_id
  WHERE m.content_item_id = e.id AND m.type_alias = 'com_jed.extension'
);
