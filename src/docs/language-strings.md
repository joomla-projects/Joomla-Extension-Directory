# Language strings — the package convention

Everything a person can read comes from a language key. That covers the obvious (templates,
form labels, toolbar buttons) and the less obvious: redirect and validation messages, exception
messages that surface in the administrator, API error bodies, scheduled-task log lines, ticket
subjects the code writes, mail templates seeded into `#__mail_templates`, the block-reason
titles seeded into `#__jed_block_reasons`, the menu items the sample-data plugin creates, and
the strings JavaScript shows (passed with `Text::script()` and read with `Joomla.Text._()`).

Where the file that needs a string runs without Joomla loaded (the VEL's CVSS parsers and the
CVE record validator, which are unit-tested that way), the class emits the *key* and its
arguments and the caller renders it — see `docs/vel/README.md`.

Where code runs outside the administrator (scheduled tasks, the queue worker, the site using an
administrator-namespace service) it loads the component's administrator language file itself
before it writes a message that will be shown in the administrator:
`Factory::getApplication()->getLanguage()->load('com_jed', JPATH_ADMINISTRATOR . '/components/com_jed')`.

## Left in English on purpose

| What | Why |
|---|---|
| `Log::add()` lines and `logTask()` *failure* details that quote an exception | Diagnostics for the server log, not text for a person on the site |
| Generated CVE record content (`timeline[].value`, the generator stamp) | Published with `lang: en`, read by the CVE Program |
| The legacy VEL feed's field vocabulary (`Live VEL`, `Very High`, `SQL Injection`) | A consumer contract: clients compare these as strings |
| The Claude audit system prompt | An instruction to the model, not to a person |
| `sql/migrate_jed3.xml` step descriptions and the tag-migration report file | One-off migration tooling; the output is a log for the engineer running it |
| The `developer_name` / `suspicious` user custom fields seeded by `script.php` | `com_fields` renders them on pages where com_jed's language is not loaded; they are editable data rows like any field created in the UI |
| Smart Search taxonomy *branch names* (`Extension Type`, `Joomla Version`) | Core's convention: the branch name is literal in the indexer and com_finder shows `PLG_FINDER_QUERY_FILTER_BRANCH_S_<NAME>`, which `plg_finder_jed.ini` provides |
| Template option defaults (`Open Sans`, the Twitter card description) | Configuration defaults inherited from the upstream joomla.org template |

## Checking

Two checks keep this honest and both are cheap to run without a Joomla installation:

1. **Plain text in code** — scan PHP for quoted strings that look like sentences, and templates
   for bare text between tags, ignoring SQL, CSS classes, URLs and `Log::add()`.
2. **Keys without definitions** — collect every `COM_*`, `PLG_*`, `MOD_*`, `TPL_*` token in PHP,
   XML and JS and check each one against the union of all `.ini` files, ignoring prefixes that
   are completed at run time (`'COM_JED_..._' . strtoupper($x)`) and `$text_prefix` values.

The second check is the one that found the largest defect so far: ~145 keys used by com_jed's
site views (dashboard, handovers, maintainer invitations, the new-extension wizard, the blocked
listing page, the category and ticket filters) that had no definition at all.
