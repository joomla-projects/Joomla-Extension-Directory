-- The two linked-extension relations (P1-23): free/paid variants and the parent extension.
--
-- This was written once before, as the "Linking Extensions" task of migrate_jed3.xml - but that
-- file had already been superseded by this plugin when the task was added to it, so the relation
-- has never actually been imported. It is ported here unchanged in substance; migrate_jed3.xml
-- is now dead in every part.
--
-- Cleared first so a re-run cannot leave a link the source no longer has. parent_confirmed goes
-- with it: it is only ever set by this import or by the JED team, and a stale confirmation is
-- worse than an absent one.
UPDATE #__jed_extensions SET parent_id = NULL, parent_confirmed = 0, variant_of_id = NULL;

-- JED3 stores each relation TWICE and the two copies do not agree. The columns
-- wqyh6_jed_extensions.parent_id / .related_free_paid_id hold the same relations as the two
-- separate tables. Counted against listings that survive the import: parent - 5 rows only in the
-- table, 16 only in the column, 1 contradicting; variant - 0 only in the table, 22 only in the
-- column, 0 contradicting.
--
-- The tables win where they disagree, because the tables are what JED3 actually rendered from:
-- view_jed_extensions_parent_id and view_jed_extensions_related_free_paid both read the tables and
-- neither reads the columns. So this pass lays down the column carrier and the next one overwrites
-- it - precedence expressed as ordering rather than as a CASE nobody can read.
--
-- The 38 column-only rows are still taken. Every one belongs to a listing with NO row at all in
-- the separate table - not one is a table row explicitly reset to 0 - so they are relations that
-- never got copied across, not relations somebody removed.
--
-- Both passes drop the same three shapes: the "no relation" marker (0 or NULL - despite the column
-- name the stored value is an extension id, not a UCM id), self-references, and targets that no
-- longer exist. The INNER JOIN onto the target does the last one silently, which is the point: a
-- relation to a row that is not there is not a relation.
UPDATE #__jed_extensions e INNER JOIN wqyh6_jed_extensions je ON je.id = e.id INNER JOIN #__jed_extensions tp ON tp.id = je.parent_id SET e.parent_id = je.parent_id, e.parent_confirmed = 1 WHERE je.parent_id IS NOT NULL AND je.parent_id <> 0 AND je.parent_id <> je.id;

UPDATE #__jed_extensions e INNER JOIN wqyh6_jed_extensions je ON je.id = e.id INNER JOIN #__jed_extensions tv ON tv.id = je.related_free_paid_id SET e.variant_of_id = je.related_free_paid_id WHERE je.related_free_paid_id IS NOT NULL AND je.related_free_paid_id <> 0 AND je.related_free_paid_id <> je.id;

-- The authoritative carrier: the two separate tables, overwriting whatever the columns just set.
--
-- parent_extension is "this extension extends that one" - 1,070 add-ons pointing at 191 distinct
-- products, VirtueMart alone collecting 268. related_free_paid is the free/paid pairing - "Foobar
-- Lite" and "Foobar Pro" as one product in two forms, 938 rows.
--
-- parent_confirmed is set for the imported stock. The column exists so a developer cannot put
-- their add-on onto somebody else's listing unaided (P1-23), but these links were already public
-- in JED3 - starting them unconfirmed would silently empty the reverse direction for the whole
-- catalogue on the day of the switch, and nobody is going to confirm a thousand rows by hand.
UPDATE #__jed_extensions e INNER JOIN wqyh6_jed_extensions_parent_extension p ON p.extension_id = e.id INNER JOIN #__jed_extensions t ON t.id = p.ucm_content_id SET e.parent_id = p.ucm_content_id, e.parent_confirmed = 1 WHERE p.ucm_content_id <> 0 AND p.ucm_content_id <> p.extension_id;

UPDATE #__jed_extensions e INNER JOIN wqyh6_jed_extensions_related_free_paid r ON r.extension_id = e.id INNER JOIN #__jed_extensions t ON t.id = r.ucm_content_id SET e.variant_of_id = r.ucm_content_id WHERE r.ucm_content_id <> 0 AND r.ucm_content_id <> r.extension_id;

-- Store each variant pair once. The relation is symmetric and JED3 could not make up its mind: 75
-- pairs are recorded from both sides and the rest from one. The new schema keeps one row per pair
-- and queries it in both directions, so the reverse half of a mutual pair goes here rather than
-- being kept in step forever. The surviving side is the lower extension id - arbitrary, but
-- stable, so a re-run produces the same table instead of a coin toss.
--
-- The self-join is safe in either evaluation order: the WHERE clause only ever clears the
-- higher-id side, and once that is NULL the pair no longer matches.
--
-- The parent relation is deliberately NOT de-duplicated: it is many-to-one and its two directions
-- mean different things, so A extending B while B extends A is a data question, not a duplicate.
UPDATE #__jed_extensions a INNER JOIN #__jed_extensions b ON b.id = a.variant_of_id AND b.variant_of_id = a.id SET a.variant_of_id = NULL WHERE a.id > b.id;

-- Carry both onto the active baseline revision. #__jed_extensions_history is what the edit form
-- loads and what an approval copies back onto the live row, so a baseline without these columns
-- would open the form empty and the next approval would silently clear the link. parent_confirmed
-- is deliberately absent from the history table (P1-23) and therefore not written here.
UPDATE #__jed_extensions_history h INNER JOIN #__jed_extensions e ON e.id = h.extension_id SET h.variant_of_id = e.variant_of_id, h.parent_id = e.parent_id WHERE h.active = 1;
