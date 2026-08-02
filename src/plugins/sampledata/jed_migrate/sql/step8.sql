-- Copy the uploaded packages. The previous version of this script emptied #__jed_extensions_files but never
-- filled it. wqyh6_jed_extension_files already carries extension_id, so the
-- wqyh6_jed_extensions_jed_extension_files cross reference table is not needed.
DELETE FROM #__jed_extensions_files;
INSERT INTO #__jed_extensions_files (extension_id, file, meta, created_by, originalFile) SELECT jef.extension_id, LEFT(jef.file, 255), jef.meta, IFNULL(jef.created_by, 0), LEFT(IFNULL(jef.original_file, ''), 255) FROM wqyh6_jed_extension_files jef INNER JOIN #__jed_extensions e ON e.id = jef.extension_id;
