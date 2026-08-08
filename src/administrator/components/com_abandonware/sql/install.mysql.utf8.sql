-- P1-19 - the abandonware directory as its own component.
--
-- Two tables, and the split between them is the whole design. A *report* is what somebody
-- submitted; a *case* is what the JED team works. They are not the same object and the legacy
-- `vel_abandoned` conflated them, which is why it had no status worth the name: a report is an
-- event that happened once, a case is a process with a beginning and an end.
--
-- The other reason to separate them is that most cases will never have a report at all. Three
-- automated signals - a dead link (P1-09), an update server that stopped answering (5.3) and long
-- inactivity (12.3) - are all symptoms of one thing, and 4.9 and 12.3 both insist they feed one
-- case rather than three ticket streams. That case has to be able to exist without anybody having
-- filled in a form.
--
-- Safe to run twice (P1-30): CREATE TABLE IF NOT EXISTS, INSERT IGNORE for the mail template, and
-- no DROP - those moved to reset.mysql.utf8.sql, which no manifest references. Schema changes
-- after go-live belong in sql/updates/mysql/<version>.sql; this file is kept in step so that a
-- fresh install ends up with the same schema.

-- The case: one process, from the first signal to a resolution.
CREATE TABLE IF NOT EXISTS `#__jed_abandonware_cases`
(
	`id`                  int unsigned NOT NULL AUTO_INCREMENT,

	-- The relational link 4.10 asks for, replacing the legacy free text. NULL is legitimate and
	-- expected: an abandonware report can concern something the JED never carried, and the whole
	-- point of keeping the free-text tuple below is that such a case is still workable.
	`extension_id`        int unsigned DEFAULT NULL,

	-- P1-28's stable text key. The column exists here before P1-28 populates it, because the
	-- alternative is an ALTER on a table that will by then hold live cases, and because it is the
	-- only thing that can identify an extension the JED has never listed.
	`identity_key`        varchar(64)  DEFAULT NULL,

	-- The identity tuple as free text. For a linked case this is a snapshot taken when the case
	-- was opened - a listing can be renamed or deleted underneath a case, and the case still has
	-- to say what it was about.
	`extension_name`      varchar(255) NOT NULL DEFAULT '',
	`extension_version`   varchar(100) NOT NULL DEFAULT '',
	`extension_url`       varchar(255) NOT NULL DEFAULT '',
	`developer_name`      varchar(255) NOT NULL DEFAULT '',

	-- received | reviewing | owner_contacted | grace_expired | abandoned | resolved | dismissed.
	-- See Enum\CaseStatus for what each one means and which transitions are legal.
	`status`              varchar(20)  NOT NULL DEFAULT 'received',

	-- report | linkcheck | updatecheck | inactivity | manual. What first raised it. A case raised
	-- by automation and later reported by a member of the public keeps its original source and
	-- gains a report row; the count of reports is the interesting number, not a changed source.
	`source`              varchar(20)  NOT NULL DEFAULT 'report',

	`assigned_to`         int unsigned DEFAULT NULL,

	-- At most one ticket per case, and - through the unique key below - at most one open case per
	-- extension, which together are 4.10 step 2's "at most one per extension". Same rule as P1-09.
	`ticket_id`           int unsigned DEFAULT NULL,

	-- Step 3, and the reason this table has three columns for one action. 4.10 calls the contact
	-- attempt the most important step and the one most likely to be skipped: an extension with no
	-- release for three years may simply be finished. `abandoned` is unreachable while
	-- `contact_time` is NULL, enforced in CaseService rather than left to discipline.
	`contact_time`        datetime     DEFAULT NULL,
	`contact_by`          int unsigned DEFAULT NULL,
	`contact_note`        text,

	-- When the grace period runs out. Set from the contact attempt, and what the scheduled pass
	-- compares against to move a case to grace_expired.
	`grace_until`         datetime     DEFAULT NULL,

	`abandoned_time`      datetime     DEFAULT NULL,
	`abandoned_by`        int unsigned DEFAULT NULL,

	`resolved_time`       datetime     DEFAULT NULL,
	`resolved_by`         int unsigned DEFAULT NULL,
	-- transferred | developer_responded | not_abandoned | duplicate | no_longer_listed.
	`resolution`          varchar(30)  DEFAULT NULL,

	-- Whether this case appears in the public list. Off until the case reaches `abandoned`: the
	-- legacy list published reports, which meant a single unverified submission put a developer's
	-- name on a public list. 4.10 weighs the commercial effect of a misjudgement and this is where
	-- that weighing lands - public means "the JED team concluded it", never "somebody claimed it".
	`published`           tinyint(1)   NOT NULL DEFAULT '0',

	-- Never public. Same rule as audit results (8.7).
	`internal_notes`      mediumtext,

	-- Which signals have fired, so the case can show why it exists without re-deriving it. JSON
	-- rather than columns because the set of signals will grow and each is a small object
	-- (when it fired, what it said).
	`signals`             json         DEFAULT NULL,

	`created`             datetime     NOT NULL,
	`created_by`          int unsigned NOT NULL DEFAULT '0',
	`modified`            datetime     DEFAULT NULL,
	`modified_by`         int unsigned DEFAULT NULL,
	`checked_out`         int unsigned DEFAULT NULL,
	`checked_out_time`    datetime     DEFAULT NULL,

	-- The duplicate rule, in the schema rather than in a service that has to remember it. The
	-- expression is the extension id while the case is open and NULL once it is closed, and MySQL
	-- allows any number of NULLs in a unique index - so an extension can have any number of
	-- historical cases and only ever one live one. A second signal for the same extension finds
	-- the open case and adds to it, which is precisely "one case, not three tickets".
	--
	-- Cases with no `extension_id` are outside this: two reports about the same unlisted extension
	-- are two cases until somebody merges them by hand, because nothing in the data says they are
	-- the same product.
	`open_extension_id`   int unsigned GENERATED ALWAYS AS (
		CASE WHEN `status` IN ('received', 'reviewing', 'owner_contacted', 'grace_expired', 'abandoned')
			THEN `extension_id` END
	) STORED,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_jed_abandonware_open_case` (`open_extension_id`),
	KEY `IDX_jed_abandonware_status` (`status`),
	KEY `IDX_jed_abandonware_extension` (`extension_id`),
	KEY `IDX_jed_abandonware_assigned` (`assigned_to`),
	KEY `IDX_jed_abandonware_grace` (`grace_until`),
	KEY `IDX_jed_abandonware_public` (`published`, `abandoned_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- The report: one submission by one person, at one moment.
--
-- Everything identifying the reporter lives here and nowhere else, which is what makes P1-18's
-- export and erasure a query against one table rather than a hunt. The public views never read
-- this table - the legacy `abandoneditem` view printed `reporter_fullname` on a public page, and
-- that is not carried over.
CREATE TABLE IF NOT EXISTS `#__jed_abandonware_reports`
(
	`id`                    int unsigned NOT NULL AUTO_INCREMENT,

	-- Set as soon as the report is filed: a report always joins or opens a case, so there is never
	-- a submission sitting outside the process. Nullable only so that deleting a case for good
	-- does not have to cascade into the evidence.
	`case_id`               int unsigned DEFAULT NULL,

	-- What the reporter said the extension was. Kept verbatim even when the case resolved it to a
	-- listing, because the two can disagree and the disagreement is worth seeing.
	`extension_id`          int unsigned DEFAULT NULL,
	`extension_name`        varchar(255) NOT NULL DEFAULT '',
	`extension_version`     varchar(100) NOT NULL DEFAULT '',
	`extension_url`         varchar(255) NOT NULL DEFAULT '',
	`developer_name`        varchar(255) NOT NULL DEFAULT '',
	`reason`                text,

	-- The reporter. `reporter_user_id` is 0 for a submission from a guest.
	`reporter_user_id`      int unsigned NOT NULL DEFAULT '0',
	`reporter_name`         varchar(255) NOT NULL DEFAULT '',
	`reporter_email`        varchar(255) NOT NULL DEFAULT '',
	`reporter_organisation` varchar(255) NOT NULL DEFAULT '',

	-- 4.6 lists recording consent as a P1 item for abandonware reporters. The timestamp matters as
	-- much as the flag: consent is evidence, and evidence with no date is not much.
	`consent_to_process`    tinyint(1)   NOT NULL DEFAULT '0',
	`consent_time`          datetime     DEFAULT NULL,

	-- A hash, not the address - the same decision as P1-12's hit log, and for the same reason:
	-- it still deduplicates and still supports the abuse work in P1-05 without being personal data
	-- that P1-18 then has to export and erase.
	`reporter_ip_hash`      varbinary(32) DEFAULT NULL,

	-- 1 accepted, 0 unpublished, -2 trashed. A rejected report leaves a record: an abandonware
	-- report against a competitor is a plausible abuse case (4.10), and the pattern is only
	-- visible if the rejected ones are still there.
	`state`                 tinyint(1)   NOT NULL DEFAULT '1',

	-- Where an imported row came from, and the idempotency key for the import: RSForms form 9 or
	-- 14 plus its submission id. There is no `vel_abandoned` table in the legacy database at all
	-- (P0-04 §2.2) - the 60 legacy reports exist only as RSForms submissions.
	`legacy_form_id`        int unsigned DEFAULT NULL,
	`legacy_submission_id`  int unsigned DEFAULT NULL,

	`created`               datetime     NOT NULL,
	`created_by`            int unsigned NOT NULL DEFAULT '0',
	`modified`              datetime     DEFAULT NULL,
	`modified_by`           int unsigned DEFAULT NULL,

	PRIMARY KEY (`id`),
	UNIQUE KEY `UQ_jed_abandonware_legacy` (`legacy_form_id`, `legacy_submission_id`),
	KEY `IDX_jed_abandonware_report_case` (`case_id`),
	KEY `IDX_jed_abandonware_report_extension` (`extension_id`),
	KEY `IDX_jed_abandonware_report_reporter` (`reporter_user_id`),
	KEY `IDX_jed_abandonware_report_state` (`state`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;


-- The owner contact of step 3. The grace period is a tag rather than prose in the body so the
-- date the team set is the date the developer reads.
INSERT IGNORE INTO `#__mail_templates` (`template_id`, `extension`, `language`, `subject`, `body`, `htmlbody`, `attachments`, `params`) VALUES
('com_abandonware.owner_contact', 'com_abandonware', '', 'COM_ABANDONWARE_OWNER_CONTACT_EMAIL_SUBJECT', 'COM_ABANDONWARE_OWNER_CONTACT_EMAIL_BODY', '', '', '{"tags":["sitename","extensionname","reason","graceuntil","listinglink","ticketlink"]}');
