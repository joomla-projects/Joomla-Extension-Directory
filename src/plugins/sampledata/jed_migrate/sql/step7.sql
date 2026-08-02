-- Copy the screenshots. The JED3 "order" column becomes "ordering", and every imported image is published,
-- matching how JED3 displayed them.
DELETE FROM #__jed_extensions_images;
INSERT INTO #__jed_extensions_images (extension_id, filename, state, ordering, created_by) SELECT jei.extension_id, jei.filename, 1, IFNULL(jei.`order`, 0), IFNULL(jei.created_by, 0) FROM wqyh6_jed_extensions_images jei INNER JOIN #__jed_extensions e ON e.id = jei.extension_id;
