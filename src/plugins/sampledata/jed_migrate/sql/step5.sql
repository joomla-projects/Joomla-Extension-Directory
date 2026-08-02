-- Merge the JED3 version list into #__jed_joomla_versions. Ids are preserved because
-- #__jed_extensions.joomla_versions stores them as a comma separated list - remapping them would break every
-- listing. Existing rows (in particular the Joomla 6 entries that JED3 does not know) are updated rather than
-- deleted.
INSERT INTO #__jed_joomla_versions (id, label, long_label, published) SELECT id, LEFT(label, 255), LEFT(long_label, 50), published FROM wqyh6_jed_joomla_versions ON DUPLICATE KEY UPDATE label = VALUES(label), long_label = VALUES(long_label), published = VALUES(published);
