-- Tickets, from BOTH legacy sources onto com_tickets (P1-24 section B, inventory 4.5).
--
-- JED3 ran two ticket systems one after the other and neither was migrated into the other:
--
--   wqyh6_jed_tickets        25,983 rows, Dec 2014 - May 2020, the JED's own table
--   wqyh6_rsticketspro_*      6,011 rows, Sep 2019 - Jul 2026, RSTicketsPro, still in use
--
-- Both land in #__jed_tickets. The id spaces overlap, so RSTicketsPro is offset by 100000 - well
-- clear of the JED3 maximum of 26,280 and readable in a URL, which an arbitrary re-numbering
-- would not be. Whichever row you are looking at, the legacy id is recoverable: subtract the
-- offset, or read it off internal_notes, which names the source table and id on every row.
--
-- WHAT IS DEPARTMENT 5. RSTicketsPro's fifth department is the Vulnerable Extensions List, 218
-- tickets and 627 messages. They are NOT imported: com_vel was removed under decision 8.13, the
-- new VEL is a separate component owned by the VEL team (P0-04), and TicketType values 4, 5 and 6
-- - the VEL report types - were deliberately deleted rather than re-used. Bringing the reports
-- into the JED's own ticket queue would put vulnerability correspondence somewhere it no longer
-- belongs. Recorded as accepted loss in P1-24.
--
-- WHAT IS LOST. RSTicketsPro carries priorities, departments, staff groups, ticket codes, time
-- spent, feedback scores, close dates, flags, the submitter's IP, browser and referer, and 47,260
-- rows of status history. com_tickets has a column for none of it and this migration does not
-- extend the schema. Everything except the IP, browser, referer and the status history is written
-- into internal_notes as plain text, so the JED team can still read what a ticket was - it is just
-- not queryable any more. The four that are dropped outright are personal data with no purpose in
-- the new system (P1-18) or, for the history, 47,260 rows of state transitions on tickets that are
-- 96 % closed.
--
-- Cleared first so a re-run cannot double the queue.
DELETE FROM #__jed_ticket_messages;

DELETE FROM #__jed_tickets;

-- ---------------------------------------------------------------------------------------------
-- JED3's own tickets
-- ---------------------------------------------------------------------------------------------

-- wqyh6_jed_tickets is flat: a reply is another row in the same table with parent_type_id set to
-- the ticket it answers. 11,413 rows are ticket openings, 14,570 are replies, and the tree is
-- exactly two deep - there is not one reply to a reply. So openings become #__jed_tickets and
-- replies become #__jed_ticket_messages, which is the shape com_tickets already has.
--
-- 160 replies point at a ticket that is not in the table. They are taken as tickets of their own
-- rather than dropped: they carry a subject, a body and an author, and the only thing wrong with
-- them is a parent that has already been deleted.
--
-- CATEGORIES. JED3 stores category as a bare integer with no lookup table anywhere in the source.
-- The vocabulary was reconstructed from the subject lines and confirmed with the JED team:
--
--   1 -> 6  Current Listing Support   name changes, removals, "extension unpublished"
--   2 -> 5  New Listing Support       "please review my extension", "extension approval status"
--   3 -> 8  Unpublished Support       "please publish", "PE1 error", "Wrong Unpublish Code"
--   4 -> 7  Site Technical Issues     "Email Change", "JED Login Issue", "Account Deletion"
--   5 -> 10 Reported Extension        every subject reads "Reported extension<id>"
--   6 -> 9  Reported Review           "Reported review for extension: <id>", and all 817 rows
--                                     carrying a review_id sit here
--
-- Anything else becomes 1 (Unknown). The raw value goes into internal_notes either way, so the
-- reconstruction can be revisited without going back to the JED3 database.
--
-- STATUS. The legacy values are 0, 1, 3 and 11, against com_tickets' 0 New, 1 Awaiting User,
-- 2 Awaiting JED, 3 Resolved, 4 Closed, 5 Updated - the new vocabulary was derived from the old
-- one, so 0, 1 and 3 carry straight over. 11 is outside it and holds 12 tickets, all dormant since
-- 2018/19; they become 4 (Closed) rather than 0 (New), because putting six-year-old tickets at the
-- top of the New queue would be a worse guess than calling them finished. The raw value is in
-- internal_notes.
--
-- LINKED ITEM. 6,961 tickets name an extension and 817 name a review; every one of the 817 names
-- both. The target holds one link, so the review wins - a ticket about a review is about the
-- review - and the extension id is written into internal_notes so the pair is not lost. The type
-- values are TicketType: 1 Extension, 2 Review.
--
-- understand is not carried: it is a "I have read the guidelines" checkbox and it is 0 on all
-- 25,983 rows.
INSERT INTO #__jed_tickets (id, ticket_origin, ticket_category_type, ticket_subject, ticket_text, internal_notes, uploaded_files_location, allocated_group, allocated_to, linked_item_type, linked_item_id, ticket_status, parent_id, state, ordering, created_by, created_on, modified_by, modified_on) SELECT t.id, '0', CASE t.category WHEN 1 THEN 6 WHEN 2 THEN 5 WHEN 3 THEN 8 WHEN 4 THEN 7 WHEN 5 THEN 10 WHEN 6 THEN 9 ELSE 1 END, LEFT(IFNULL(t.subject, ''), 255), t.message, CONCAT_WS(CHAR(10), CONCAT('Imported from JED3 jed_tickets id ', t.id, '; legacy category ', IFNULL(t.category, 0), ', legacy status ', IFNULL(t.status, 0), '.'), CASE WHEN t.parent_type_id <> 0 THEN CONCAT('Was a reply to ticket ', t.parent_type_id, ', which did not survive the migration.') END, CASE WHEN TRIM(IFNULL(t.reason, '')) <> '' THEN CONCAT('Reason: ', t.reason) END, CASE WHEN t.review_id > 0 AND t.extension_id > 0 THEN CONCAT('Also concerns extension ', t.extension_id, '.') END, CASE WHEN NULLIF(t.assigned_to_date, '0000-00-00 00:00:00') IS NOT NULL THEN CONCAT('Assigned ', DATE_FORMAT(t.assigned_to_date, '%Y-%m-%d %H:%i'), '.') END, (SELECT CONCAT('Attachments: ', GROUP_CONCAT(f.file ORDER BY f.id SEPARATOR ', ')) FROM wqyh6_jed_ticket_files f WHERE f.ticket_id = t.id)), LEFT(IFNULL((SELECT GROUP_CONCAT(f.file ORDER BY f.id SEPARATOR ', ') FROM wqyh6_jed_ticket_files f WHERE f.ticket_id = t.id), ''), 255), 0, IFNULL(t.assigned_to, 0), CASE WHEN t.review_id > 0 THEN 2 WHEN t.extension_id > 0 THEN 1 ELSE 0 END, CASE WHEN t.review_id > 0 THEN t.review_id WHEN t.extension_id > 0 THEN t.extension_id ELSE 0 END, CASE WHEN t.status BETWEEN 0 AND 5 THEN t.status ELSE 4 END, 0, 1, 0, IFNULL(t.created_by, 0), NULLIF(t.created, '0000-00-00 00:00:00'), NULLIF(t.assigned_to, 0), NULLIF(t.modified, '0000-00-00 00:00:00') FROM wqyh6_jed_tickets t WHERE t.parent_type_id = 0 OR NOT EXISTS (SELECT 1 FROM wqyh6_jed_tickets p WHERE p.id = t.parent_type_id);

-- The replies. message_direction is 1 for a message coming in from the user and 0 for one going
-- out from the JED team (TicketController); JED3 records no direction, so it is derived from the
-- author: a reply written by whoever opened the ticket is incoming, anything else is the team
-- answering. internal is 0 throughout - JED3 had no notion of a staff-only reply, and defaulting
-- to 1 would hide the whole imported correspondence from the developers it belongs to.
INSERT INTO #__jed_ticket_messages (id, ticket_id, subject, message, message_direction, internal, created_by, created_on) SELECT c.id, c.parent_type_id, LEFT(IFNULL(c.subject, ''), 255), c.message, CASE WHEN c.created_by = t.created_by THEN 1 ELSE 0 END, 0, IFNULL(c.created_by, 0), NULLIF(c.created, '0000-00-00 00:00:00') FROM wqyh6_jed_tickets c INNER JOIN #__jed_tickets t ON t.id = c.parent_type_id WHERE c.parent_type_id <> 0;

-- ---------------------------------------------------------------------------------------------
-- RSTicketsPro
-- ---------------------------------------------------------------------------------------------

-- Staging, because three things have to be gathered per ticket before the row can be written and
-- doing it with correlated subqueries in the INSERT would read wqyh6_rsticketspro_ticket_messages
-- once per ticket.
DROP TABLE IF EXISTS combine_jed_rsp;

CREATE TABLE combine_jed_rsp (ticket_id INT NOT NULL, first_message_id INT NULL, body MEDIUMTEXT NULL, extension_id INT NULL, review_id INT NULL, extension_name VARCHAR(255) NULL, review_title VARCHAR(255) NULL, notes MEDIUMTEXT NULL, PRIMARY KEY (ticket_id)) ENGINE=INNODB DEFAULT CHARSET=utf8mb4;

-- GROUP_CONCAT truncates at 1024 bytes by default, which would silently cut the staff notes.
SET SESSION group_concat_max_len = 65535;

-- Department 5 (VEL) is excluded here, once, so every statement below inherits it.
INSERT INTO combine_jed_rsp (ticket_id, first_message_id) SELECT t.id, (SELECT MIN(m.id) FROM wqyh6_rsticketspro_ticket_messages m WHERE m.ticket_id = t.id) FROM wqyh6_rsticketspro_tickets t WHERE t.department_id <> 5;

-- RSTicketsPro has no body column on the ticket: what the customer wrote is the first row of
-- wqyh6_rsticketspro_ticket_messages. Every one of the 6,011 tickets has at least one.
UPDATE combine_jed_rsp c INNER JOIN wqyh6_rsticketspro_ticket_messages m ON m.id = c.first_message_id SET c.body = m.message;

-- The custom fields are what the submission form asked for and they are the only thing that ties
-- an RSTicketsPro ticket to a listing. Matched by name rather than by id, because the same field
-- exists once per department under four different ids.
UPDATE combine_jed_rsp c INNER JOIN wqyh6_rsticketspro_custom_fields_values v ON v.ticket_id = c.ticket_id INNER JOIN wqyh6_rsticketspro_custom_fields f ON f.id = v.custom_field_id SET c.extension_id = CASE WHEN TRIM(v.value) REGEXP '^[0-9]+$' THEN CAST(TRIM(v.value) AS UNSIGNED) END WHERE f.name = 'extension-id';

UPDATE combine_jed_rsp c INNER JOIN wqyh6_rsticketspro_custom_fields_values v ON v.ticket_id = c.ticket_id INNER JOIN wqyh6_rsticketspro_custom_fields f ON f.id = v.custom_field_id SET c.review_id = CASE WHEN TRIM(v.value) REGEXP '^[0-9]+$' THEN CAST(TRIM(v.value) AS UNSIGNED) END WHERE f.name = 'review-id';

UPDATE combine_jed_rsp c INNER JOIN wqyh6_rsticketspro_custom_fields_values v ON v.ticket_id = c.ticket_id INNER JOIN wqyh6_rsticketspro_custom_fields f ON f.id = v.custom_field_id SET c.extension_name = LEFT(TRIM(v.value), 255) WHERE f.name = 'extension-name';

UPDATE combine_jed_rsp c INNER JOIN wqyh6_rsticketspro_custom_fields_values v ON v.ticket_id = c.ticket_id INNER JOIN wqyh6_rsticketspro_custom_fields f ON f.id = v.custom_field_id SET c.review_title = LEFT(TRIM(v.value), 255) WHERE f.name = 'review-title';

-- Staff notes. com_tickets keeps its internal notes in one text column on the ticket rather than
-- as rows, so they are folded together in date order.
UPDATE combine_jed_rsp c SET c.notes = (SELECT GROUP_CONCAT(CONCAT(IFNULL(DATE_FORMAT(NULLIF(n.date, '0000-00-00 00:00:00'), '%Y-%m-%d'), 'undated'), ' (user ', n.user_id, '): ', n.text) ORDER BY n.id SEPARATOR '\n') FROM wqyh6_rsticketspro_ticket_notes n WHERE n.ticket_id = c.ticket_id);

-- The tickets themselves.
--
--   department  -> ticket_category_type   1 Listing -> 6 Current Listing Support
--                                         2 Reported Extensions -> 10 Reported Extension
--                                         3 Reported Reviews -> 9 Reported Review
--                                         4 TBD (unpublished, unused) -> 1 Unknown
--   status      -> ticket_status          1 Open -> 2 Awaiting JED, 2 Closed -> 4 Closed,
--                                         3 Answered -> 1 Awaiting User
--   staff group -> allocated_group        1 Support Staff -> 5, 2 Reported Extensions Staff -> 3,
--                                         3 Reported Reviews Staff -> 4
--   staff       -> allocated_to           through wqyh6_rsticketspro_staff.user_id, which is the
--                                         Joomla user id; staff_id is not.
--
-- ticket_origin is 0 Registered User / 1 Joomla! Team, and is decided by whether the customer is
-- themselves a member of staff - a support person opening a ticket is the team, not a user.
--
-- 42 tickets name a customer with no account. The id is kept rather than being reset to 0: it is
-- the only remaining trace of who wrote them, and 0 would read as "written by nobody".
INSERT INTO #__jed_tickets (id, ticket_origin, ticket_category_type, ticket_subject, ticket_text, internal_notes, uploaded_files_location, allocated_group, allocated_to, linked_item_type, linked_item_id, ticket_status, parent_id, state, ordering, created_by, created_on, modified_by, modified_on) SELECT 100000 + t.id, CASE WHEN cust.id IS NOT NULL THEN '1' ELSE '0' END, CASE t.department_id WHEN 1 THEN 6 WHEN 2 THEN 10 WHEN 3 THEN 9 ELSE 1 END, LEFT(IFNULL(t.subject, ''), 255), c.body, CONCAT_WS(CHAR(10), CONCAT('Imported from RSTicketsPro ticket ', t.id, IFNULL(CONCAT(' (code ', NULLIF(TRIM(t.code), ''), ')'), ''), '.'), CONCAT('Department: ', IFNULL(d.name, CONCAT('id ', t.department_id)), '. Status: ', IFNULL(st.name, CONCAT('id ', t.status_id)), '. Priority: ', IFNULL(pr.name, CONCAT('id ', t.priority_id)), '.'), CASE WHEN NULLIF(t.closed, '0000-00-00 00:00:00') IS NOT NULL THEN CONCAT('Closed ', DATE_FORMAT(t.closed, '%Y-%m-%d %H:%i'), '.') END, CASE WHEN IFNULL(t.time_spent, 0) > 0 THEN CONCAT('Time spent: ', t.time_spent, '.') END, CASE WHEN IFNULL(t.feedback, 0) <> 0 THEN CONCAT('Feedback score: ', t.feedback, '.') END, CASE WHEN IFNULL(t.flagged, 0) = 1 THEN 'Flagged in RSTicketsPro.' END, CASE WHEN TRIM(IFNULL(t.alternative_email, '')) <> '' THEN CONCAT('Alternative email given: ', t.alternative_email) END, CASE WHEN c.extension_name IS NOT NULL THEN CONCAT('Extension named on the form: ', c.extension_name) END, CASE WHEN c.review_title IS NOT NULL THEN CONCAT('Review titled on the form: ', c.review_title) END, CASE WHEN c.review_id > 0 AND c.extension_id > 0 THEN CONCAT('Also concerns extension ', c.extension_id, '.') END, c.notes), '', CASE stf.group_id WHEN 1 THEN 5 WHEN 2 THEN 3 WHEN 3 THEN 4 ELSE 0 END, IFNULL(stf.user_id, 0), CASE WHEN c.review_id > 0 THEN 2 WHEN c.extension_id > 0 THEN 1 ELSE 0 END, CASE WHEN c.review_id > 0 THEN c.review_id WHEN c.extension_id > 0 THEN c.extension_id ELSE 0 END, CASE t.status_id WHEN 1 THEN 2 WHEN 2 THEN 4 WHEN 3 THEN 1 ELSE 0 END, 0, 1, 0, IFNULL(t.customer_id, 0), NULLIF(t.date, '0000-00-00 00:00:00'), stf.user_id, NULLIF(t.last_reply, '0000-00-00 00:00:00') FROM wqyh6_rsticketspro_tickets t INNER JOIN combine_jed_rsp c ON c.ticket_id = t.id LEFT JOIN wqyh6_rsticketspro_departments d ON d.id = t.department_id LEFT JOIN wqyh6_rsticketspro_statuses st ON st.id = t.status_id LEFT JOIN wqyh6_rsticketspro_priorities pr ON pr.id = t.priority_id LEFT JOIN wqyh6_rsticketspro_staff stf ON stf.id = t.staff_id LEFT JOIN wqyh6_rsticketspro_staff cust ON cust.user_id = t.customer_id;

-- Every message except the first, which is already the ticket body. RSTicketsPro does record the
-- direction, in submitted_by_staff, so it is used rather than inferred.
INSERT INTO #__jed_ticket_messages (id, ticket_id, subject, message, message_direction, internal, created_by, created_on) SELECT 100000 + m.id, 100000 + m.ticket_id, '', m.message, CASE WHEN IFNULL(m.submitted_by_staff, 0) = 1 THEN 0 ELSE 1 END, 0, IFNULL(m.user_id, 0), NULLIF(m.date, '0000-00-00 00:00:00') FROM wqyh6_rsticketspro_ticket_messages m INNER JOIN combine_jed_rsp c ON c.ticket_id = m.ticket_id WHERE m.id <> c.first_message_id;

DROP TABLE IF EXISTS combine_jed_rsp;

-- ---------------------------------------------------------------------------------------------
-- Drop the links that point at nothing
-- ---------------------------------------------------------------------------------------------

-- 266 tickets name an extension and 518 name a review that no longer exists - listings and reviews
-- deleted in JED3 long after the ticket about them was closed. The link is cleared rather than
-- kept, on the same rule the linked-extension import follows: a relation to a row that is not
-- there is not a relation. Here it is worse than useless, because linked_item_type drives a link
-- on the ticket view and a kept one leads to a 404.
--
-- The id is written into internal_notes first, in the same statement - MySQL evaluates SET
-- assignments left to right, so linked_item_id still holds the old value at that point. Nothing is
-- lost: the ticket still says what it was about, in the one column that is not a foreign key.
--
-- These run after both sources are in, so one pass covers them.
UPDATE #__jed_tickets t LEFT JOIN #__jed_extensions e ON e.id = t.linked_item_id SET t.internal_notes = CONCAT_WS(CHAR(10), NULLIF(t.internal_notes, ''), CONCAT('Linked extension ', t.linked_item_id, ' no longer exists; the link was dropped on import.')), t.linked_item_type = 0, t.linked_item_id = 0 WHERE t.linked_item_type = 1 AND e.id IS NULL;

UPDATE #__jed_tickets t LEFT JOIN #__jed_reviews r ON r.id = t.linked_item_id SET t.internal_notes = CONCAT_WS(CHAR(10), NULLIF(t.internal_notes, ''), CONCAT('Linked review ', t.linked_item_id, ' no longer exists; the link was dropped on import.')), t.linked_item_type = 0, t.linked_item_id = 0 WHERE t.linked_item_type = 2 AND r.id IS NULL;

-- allocated_to is deliberately NOT cleaned the same way. 20 tickets are assigned to an account that
-- no longer exists - staff who have left - and that field renders as a blank name rather than as a
-- broken link, so keeping the id costs nothing and is the only remaining record of who was handling
-- the ticket.
