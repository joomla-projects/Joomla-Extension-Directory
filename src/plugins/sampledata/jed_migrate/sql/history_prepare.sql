-- Prepare the extension history import.
-- Work out how wqyh6_ucm_history refers to a listing: Joomla stores ucm_item_id as the primary
-- key of the content type's own table, which for a Fabrik-backed type is either
-- wqyh6_jed_extensions.id or wqyh6_ucm_content.core_content_id. The schema alone does not say
-- which, so both are counted and the interpretation with more matches wins.
-- The resolved content type id and the batch boundaries for the import steps go into
-- combine_jed_history_cfg. They CANNOT be held in session variables: every sampledata step is its
-- own AJAX request on its own database connection, so a @variable set here would be NULL by the
-- time the import steps run and they would silently import no revisions at all.
DELETE FROM #__jed_extensions_history;

DROP TABLE IF EXISTS combine_jed_history_cfg;

CREATE TABLE combine_jed_history_cfg (
  ucm_type_id INT UNSIGNED NULL
) ENGINE=INNODB;

-- Always exactly one row, even when the content type is absent - the import steps then match
-- nothing and the run still completes with just the active baseline revision.
INSERT INTO combine_jed_history_cfg (ucm_type_id)
SELECT (SELECT type_id FROM wqyh6_content_types WHERE type_alias = 'com_jed.extension' LIMIT 1);

SET @jed_ext_type_id = (SELECT ucm_type_id FROM combine_jed_history_cfg);

SET @jed_hist_by_ext = (SELECT COUNT(*) FROM wqyh6_ucm_history h INNER JOIN combine_jed_extensions cj ON cj.id = h.ucm_item_id WHERE h.ucm_type_id = @jed_ext_type_id);

SET @jed_hist_by_ucm = (SELECT COUNT(*) FROM wqyh6_ucm_history h INNER JOIN combine_jed_extensions cj ON cj.core_content_id = h.ucm_item_id WHERE h.ucm_type_id = @jed_ext_type_id);

-- Ledger of revisions already imported. The import steps consult and extend it so that
-- re-running one of them is safe: a browser timeout does not necessarily mean the statement
-- failed on the server, so a retry must not insert the same revisions a second time.
DROP TABLE IF EXISTS combine_jed_history_done;

CREATE TABLE combine_jed_history_done (version_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY (version_id)) ENGINE=INNODB;

DROP TABLE IF EXISTS combine_jed_history_map;

CREATE TABLE combine_jed_history_map (ucm_item_id INT UNSIGNED NOT NULL, extension_id INT UNSIGNED NOT NULL, PRIMARY KEY (ucm_item_id)) ENGINE=INNODB;

INSERT IGNORE INTO combine_jed_history_map (ucm_item_id, extension_id) SELECT cj.id, cj.id FROM combine_jed_extensions cj WHERE @jed_hist_by_ext >= @jed_hist_by_ucm;

INSERT IGNORE INTO combine_jed_history_map (ucm_item_id, extension_id) SELECT cj.core_content_id, cj.id FROM combine_jed_extensions cj WHERE @jed_hist_by_ext < @jed_hist_by_ucm;

-- Equal-row-count boundaries for the history import steps. NTILE spreads the revisions evenly
-- across the batches regardless of how their version_ids are distributed, so no single step
-- inherits a disproportionate share of the work.
DROP TABLE IF EXISTS combine_jed_history_batches;

CREATE TABLE combine_jed_history_batches (
  batch INT UNSIGNED NOT NULL,
  lo BIGINT UNSIGNED NULL,
  hi BIGINT UNSIGNED NULL,
  PRIMARY KEY (batch)
) ENGINE=INNODB;

INSERT INTO combine_jed_history_batches (batch, lo, hi)
SELECT t.batch, MIN(t.version_id), MAX(t.version_id)
FROM (
  SELECT h.version_id, NTILE({{BATCHES}}) OVER (ORDER BY h.version_id) AS batch
  FROM wqyh6_ucm_history h
  INNER JOIN combine_jed_history_map m ON m.ucm_item_id = h.ucm_item_id
  WHERE h.ucm_type_id = (SELECT ucm_type_id FROM combine_jed_history_cfg)
) t
GROUP BY t.batch;
