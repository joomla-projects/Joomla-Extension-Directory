<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Sampledata.Jed_Migrate
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
 */

use Jed\Component\Jed\Administrator\Parser\VideoParser;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Categories\Administrator\Table\CategoryTable;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseDriver;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sampledata - JED3 migration plugin.
 *
 * Runs the JED3 -> JED4 migration that used to live in
 * administrator/components/com_jed/sql/migrate_jed3.xml, driven by the com_jed admin view
 * Copyjed3data. That view executed every statement of every task inside a single request,
 * which does not survive the real data volume - one PHP timeout and the run is lost with no
 * way to resume.
 *
 * Here each <task> of the old file is one sampledata step, so each step is its own AJAX
 * request with its own time and memory budget, reports success or failure on its own, and
 * can be retried without redoing the steps before it. The SQL is unchanged and lives in
 * sql/step1.sql .. sql/step16.sql.
 *
 * The connection to the JED3 database comes from the com_jed component options
 * (jed3_db_database_name and jed3_db_prefix). Both databases must live on the same server,
 * because the SQL reaches into the source with a <database>.<prefix>_table reference.
 *
 * @since  4.0.0
 */
class PlgSampledataJed_Migrate extends CMSPlugin
{
    use DatabaseAwareTrait;

    /**
     * Database object
     *
     * @var    DatabaseDriver
     *
     * @since  4.0.0
     */
    protected $db;

    /**
     * Load language files automatically.
     *
     * @var    boolean
     *
     * @since  4.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * The placeholder prefix used inside the step SQL for the JED3 tables. It is rewritten
     * to "<jed3 database>.<jed3 prefix>_" before a statement runs - the same contract the
     * old JedmigrateHelper::doSql() used, so the SQL did not have to change.
     *
     * @var    string
     *
     * @since  4.0.0
     */
    private const SOURCE_PLACEHOLDER = 'wqyh6_';

    /**
     * How many steps the extension history import is spread over.
     *
     * The history import is by far the heaviest part of the migration: every revision in
     * wqyh6_ucm_history is parsed out of a JSON blob field by field. Raise this if a batch
     * step still exceeds the request time limit - it is the only value that needs changing,
     * the SQL and the step numbering follow from it.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const HISTORY_BATCHES = 16;

    /**
     * Total number of steps: ten fixed migration steps, the history prepare step, one step per
     * history batch, then the baseline revision, the hit aggregate, the RSForms staging, the
     * abandonware import and the cleanup.
     *
     * @var    integer
     *
     * @since  4.0.0
     */
    private const STEP_COUNT = 15 + self::HISTORY_BATCHES;

    /**
     * Constructor.
     *
     * @param   object  $subject  The object to observe.
     * @param   array   $config   An optional associative array of configuration settings.
     *
     * @since   4.0.0
     */
    public function __construct(&$subject, $config = [])
    {
        $this->setDatabase(Factory::getContainer()->get(DatabaseDriver::class));
        parent::__construct($subject, $config);
        $this->setApplication(Factory::getApplication());
    }

    /**
     * Get an overview of the proposed sampledata.
     *
     * @return  stdClass  Object containing the name, title, description, icon and steps.
     *
     * @since   4.0.0
     */
    public function onSampledataGetOverview()
    {
        $data              = new stdClass();
        $data->name        = $this->_name;
        $data->title       = Text::_('PLG_SAMPLEDATA_JED_MIGRATE_OVERVIEW_TITLE');
        $data->description = Text::_('PLG_SAMPLEDATA_JED_MIGRATE_OVERVIEW_DESC');
        $data->icon        = 'database';
        $data->steps       = self::STEP_COUNT;

        return $data;
    }

    // The sampledata API dispatches to onAjaxSampledataApplyStepN, so each step needs its own
    // method. They all defer to applyStep(), which resolves the step through stepPlan().
    // Step 1 additionally checks that the source database is configured and reachable, so a
    // misconfiguration is reported before anything is written.

    public function onAjaxSampledataApplyStep1()
    {
        return $this->applyStep(1);
    }

    public function onAjaxSampledataApplyStep2()
    {
        return $this->applyStep(2);
    }

    public function onAjaxSampledataApplyStep3()
    {
        return $this->applyStep(3);
    }

    public function onAjaxSampledataApplyStep4()
    {
        return $this->applyStep(4);
    }

    public function onAjaxSampledataApplyStep5()
    {
        return $this->applyStep(5);
    }

    public function onAjaxSampledataApplyStep6()
    {
        return $this->applyStep(6);
    }

    public function onAjaxSampledataApplyStep7()
    {
        return $this->applyStep(7);
    }

    public function onAjaxSampledataApplyStep8()
    {
        return $this->applyStep(8);
    }

    public function onAjaxSampledataApplyStep9()
    {
        return $this->applyStep(9);
    }

    public function onAjaxSampledataApplyStep10()
    {
        return $this->applyStep(10);
    }

    public function onAjaxSampledataApplyStep11()
    {
        return $this->applyStep(11);
    }

    public function onAjaxSampledataApplyStep12()
    {
        return $this->applyStep(12);
    }

    public function onAjaxSampledataApplyStep13()
    {
        return $this->applyStep(13);
    }

    public function onAjaxSampledataApplyStep14()
    {
        return $this->applyStep(14);
    }

    public function onAjaxSampledataApplyStep15()
    {
        return $this->applyStep(15);
    }

    public function onAjaxSampledataApplyStep16()
    {
        return $this->applyStep(16);
    }

    public function onAjaxSampledataApplyStep17()
    {
        return $this->applyStep(17);
    }

    public function onAjaxSampledataApplyStep18()
    {
        return $this->applyStep(18);
    }

    public function onAjaxSampledataApplyStep19()
    {
        return $this->applyStep(19);
    }

    public function onAjaxSampledataApplyStep20()
    {
        return $this->applyStep(20);
    }

    public function onAjaxSampledataApplyStep21()
    {
        return $this->applyStep(21);
    }

    public function onAjaxSampledataApplyStep22()
    {
        return $this->applyStep(22);
    }

    public function onAjaxSampledataApplyStep23()
    {
        return $this->applyStep(23);
    }

    public function onAjaxSampledataApplyStep24()
    {
        return $this->applyStep(24);
    }

    public function onAjaxSampledataApplyStep25()
    {
        return $this->applyStep(25);
    }

    public function onAjaxSampledataApplyStep26()
    {
        return $this->applyStep(26);
    }

    public function onAjaxSampledataApplyStep27()
    {
        return $this->applyStep(27);
    }

    public function onAjaxSampledataApplyStep28()
    {
        return $this->applyStep(28);
    }

    public function onAjaxSampledataApplyStep29()
    {
        return $this->applyStep(29);
    }

    public function onAjaxSampledataApplyStep30()
    {
        return $this->applyStep(30);
    }

    /**
     * Map every step number onto the SQL file that implements it.
     *
     * Steps 1-10 are the fixed migration tasks. Step 11 prepares the history import, the next
     * HISTORY_BATCHES steps each import one batch from the same parameterised file, and the
     * final three add the baseline revision, stage the RSForms data and clean up.
     *
     * @return  array<int, array{file: string, batch?: int, label: string}>
     *
     * @since   4.0.0
     */
    private function stepPlan(): array
    {
        $plan = [];

        for ($i = 1; $i <= 10; $i++) {
            $plan[$i] = ['file' => 'step' . $i . '.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_STEP' . $i . '_SUCCESS'];

            // Step 4 copies the categories in with JED3's own lft/rgt values, which do not
            // fit the target's shared nested set - rebuild it before anything reads the tree.
            if ($i === 4) {
                $plan[$i]['after'] = 'rebuildCategoryTree';
            }

            // Step 6 is where #__jed_extensions is filled, so the videos it brought in are
            // normalised straight afterwards - once, at import, never on render (8.4).
            if ($i === 6) {
                $plan[$i]['after'] = 'normaliseVideos';
            }
        }

        $plan[11] = ['file' => 'history_prepare.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_PREPARE_SUCCESS'];

        for ($batch = 1; $batch <= self::HISTORY_BATCHES; $batch++) {
            $plan[11 + $batch] = [
                'file'  => 'history_batch.sql',
                'batch' => $batch,
                'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_BATCH_SUCCESS',
            ];
        }

        $next = 11 + self::HISTORY_BATCHES;

        $plan[++$next] = ['file' => 'history_baseline.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HISTORY_BASELINE_SUCCESS'];
        // After the listings exist, because the aggregate joins them to drop hits for extensions
        // that never came across (P1-12 item 7).
        $plan[++$next] = ['file' => 'hits.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_HITS_SUCCESS'];
        $plan[++$next] = ['file' => 'rsforms.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_RSFORMS_SUCCESS'];
        // After rsforms.sql, which stages forms 9 and 14, and after the listings exist, because it
        // resolves each report's free-text extension name against #__jed_extensions (P1-19).
        $plan[++$next] = ['file' => 'abandonware.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_ABANDONWARE_SUCCESS'];
        $plan[++$next] = ['file' => 'cleanup.sql', 'label' => 'PLG_SAMPLEDATA_JED_MIGRATE_CLEANUP_SUCCESS'];

        return $plan;
    }

    /**
     * Run one migration step.
     *
     * @param   integer  $step  The step number, 1 based.
     *
     * @return  array|void  Will be converted into the JSON response to the module.
     *
     * @since   4.0.0
     */
    private function applyStep(int $step)
    {
        if ($this->getApplication()->getInput()->get('type') !== $this->_name) {
            return;
        }

        if ($step === 1) {
            $configError = $this->checkSourceDatabase();

            if ($configError !== null) {
                return ['success' => false, 'message' => $configError];
            }
        }

        $plan = $this->stepPlan();

        if (!isset($plan[$step])) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', 'step ' . $step),
            ];
        }

        $spec = $plan[$step];
        $file = __DIR__ . '/sql/' . $spec['file'];

        if (!is_file($file)) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', basename($file)),
            ];
        }

        $sql = file_get_contents($file);

        if ($sql === false) {
            return [
                'success' => false,
                'message' => Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_MISSING_FILE', basename($file)),
            ];
        }

        // {{BATCH}} selects the slice of wqyh6_ucm_history this step imports, {{BATCHES}} tells
        // the prepare step how many slices to cut. Both are plugin constants, never user input.
        $sql = str_replace(
            ['{{BATCH}}', '{{BATCHES}}'],
            [(string) ($spec['batch'] ?? 0), (string) self::HISTORY_BATCHES],
            $sql
        );

        $db      = $this->getDatabase();
        $queries = $this->splitQueries($this->applySourcePrefix($sql));
        $count   = 0;

        foreach ($queries as $query) {
            $db->setQuery($query);

            try {
                $db->execute();
                $count++;
            } catch (\RuntimeException $e) {
                // Report which statement failed. Unlike the old runner this does not kill
                // the request with exit(), so the step can be corrected and retried on its
                // own instead of restarting the whole migration.
                return [
                    'success' => false,
                    'message' => Text::sprintf(
                        'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_STEP',
                        $step,
                        $count + 1,
                        $e->getMessage(),
                        $this->shorten($query)
                    ),
                ];
            }
        }

        if (isset($spec['after']) && is_callable([$this, $spec['after']])) {
            $error = $this->{$spec['after']}();

            if ($error !== null) {
                return ['success' => false, 'message' => $error];
            }
        }

        return [
            'success' => true,
            'message' => isset($spec['batch'])
                ? Text::sprintf($spec['label'], $spec['batch'], self::HISTORY_BATCHES, $count)
                : Text::sprintf($spec['label'], $count),
        ];
    }

    /**
     * Rebuild the category nested set after the JED3 categories have been copied in.
     *
     * The copy takes the JED3 lft/rgt values verbatim, but Joomla keeps ONE nested set across
     * every extension in #__categories - com_content, com_contact and com_jed all live in the
     * same tree under a single ROOT row. The imported numbering therefore does not fit inside
     * the target's existing ROOT, and any category landing outside its lft/rgt range becomes
     * unreachable by tree traversal: no subcategory listing, no breadcrumb, no SEF path.
     *
     * Recomputing lft/rgt/level from parent_id puts every row back inside the tree. This has to
     * happen in PHP - it is a recursive walk that the plain SQL steps cannot express.
     *
     * @return string|null  An error message, or null on success.
     *
     * @since 4.0.0
     */
    private function rebuildCategoryTree(): ?string
    {
        try {
            // com_categories owns the table class, and its namespace is only registered once
            // the component has been booted - instantiating the class directly fails outside a
            // request that already loaded it.
            $table = Factory::getApplication()
                ->bootComponent('com_categories')
                ->getMVCFactory()
                ->createTable('Category', 'Administrator', ['dbo' => $this->getDatabase()]);

            if (!$table->rebuild()) {
                return Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_CATEGORY_REBUILD',
                    $table->getError() ?: 'unknown error'
                );
            }
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_CATEGORY_REBUILD', $e->getMessage());
        }

        return null;
    }

    /**
     * Normalise the imported `video` values into provider + id (`P1-11`).
     *
     * A PHP step rather than SQL because the parser is PHP: the patterns involved - a query
     * string where `v` is not the first parameter, `{youtube}id|650{/youtube}`, a scheme that is
     * simply missing - are not something a REGEXP_REPLACE should be asked to get right, and
     * getting one of them subtly wrong across 1,258 rows is how a catalogue ends up full of
     * embeds pointing at nothing.
     *
     * The raw column is left exactly as it was. It is what the developer typed, it is what the
     * clean-up report has to quote back, and overwriting it would destroy the only evidence of
     * what a value that failed to convert actually said.
     *
     * @return  string|null  An error message, or null on success.
     *
     * @since   4.1.0
     */
    private function normaliseVideos(): ?string
    {
        try {
            $db = $this->getDatabase();

            $rows = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'video']))
                    ->from($db->quoteName('#__jed_extensions'))
                    ->where('TRIM(IFNULL(' . $db->quoteName('video') . ", '')) <> ''")
            )->loadAssocList();

            $converted = 0;

            foreach ((array) $rows as $row) {
                $video = VideoParser::parse((string) $row['video']);

                if ($video === null) {
                    // Left NULL on purpose. The row stays on the clean-up report rather than
                    // being quietly stored as something it is not.
                    continue;
                }

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__jed_extensions'))
                        ->set($db->quoteName('video_provider') . ' = ' . $db->quote($video->provider))
                        ->set($db->quoteName('video_id') . ' = ' . $db->quote($video->id))
                        ->where($db->quoteName('id') . ' = ' . (int) $row['id'])
                )->execute();

                $converted++;
            }

            Factory::getApplication()->enqueueMessage(
                Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_VIDEOS_NORMALISED',
                    $converted,
                    \count((array) $rows) - $converted
                )
            );
        } catch (\Throwable $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_VIDEOS', $e->getMessage());
        }

        return null;
    }

    /**
     * Verify that the JED3 source database is configured and reachable before the first
     * step writes anything.
     *
     * @return  string|null  An error message, or null when the source looks usable.
     *
     * @since   4.0.0
     */
    private function checkSourceDatabase(): ?string
    {
        $params   = ComponentHelper::getParams('com_jed');
        $database = (string) $params->get('jed3_db_database_name', '');
        $prefix   = (string) $params->get('jed3_db_prefix', '');

        if ($database === '' || $prefix === '') {
            return Text::_('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_NOT_CONFIGURED');
        }

        $db = $this->getDatabase();

        try {
            $db->setQuery(
                'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '
                . $db->quote($database) . ' AND table_name = '
                . $db->quote($prefix . '_jed_extensions')
            );

            if (!$db->loadResult()) {
                return Text::sprintf(
                    'PLG_SAMPLEDATA_JED_MIGRATE_ERROR_SOURCE_MISSING',
                    $database . '.' . $prefix . '_jed_extensions'
                );
            }
        } catch (\RuntimeException $e) {
            return Text::sprintf('PLG_SAMPLEDATA_JED_MIGRATE_ERROR_SOURCE_UNREADABLE', $e->getMessage());
        }

        return null;
    }

    /**
     * Rewrite the source table placeholder to the configured JED3 database and prefix.
     *
     * @param   string  $sql  The raw step SQL.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function applySourcePrefix(string $sql): string
    {
        $params = ComponentHelper::getParams('com_jed');

        $replacement = (string) $params->get('jed3_db_database_name', '')
            . '.' . (string) $params->get('jed3_db_prefix', '') . '_';

        return str_replace(self::SOURCE_PLACEHOLDER, $replacement, $sql);
    }

    /**
     * Split a step file into individual statements.
     *
     * This deliberately does not reuse the splitter from plg_sampledata_jed: that one strips
     * every line beginning with "#" as a comment, which would silently delete any statement
     * that happens to start with the Joomla table prefix "#__". Here only "--" starts a
     * comment, and semicolons inside quoted strings do not split a statement.
     *
     * @param   string  $sql  The step file contents.
     *
     * @return  string[]  The statements, with blanks and comment-only entries removed.
     *
     * @since   4.0.0
     */
    private function splitQueries(string $sql): array
    {
        $queries = [];
        $current = '';
        $quote   = null;
        $length  = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];

            if ($quote !== null) {
                $current .= $char;

                if ($char === '\\' && $i + 1 < $length) {
                    // Keep an escaped character with its backslash so an escaped quote
                    // does not end the string.
                    $current .= $sql[++$i];
                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote    = $char;
                $current .= $char;
                continue;
            }

            // A "--" comment runs to the end of the line. "#" is not treated as a comment
            // because "#__" is the table prefix.
            if ($char === '-' && $i + 1 < $length && $sql[$i + 1] === '-') {
                while ($i < $length && $sql[$i] !== "\n") {
                    $i++;
                }

                $current .= "\n";
                continue;
            }

            if ($char === ';') {
                $queries[] = $current;
                $current   = '';
                continue;
            }

            $current .= $char;
        }

        $queries[] = $current;

        return array_values(array_filter(array_map('trim', $queries), static fn ($q) => $q !== ''));
    }

    /**
     * Shorten a statement for an error message.
     *
     * @param   string  $query  The statement.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function shorten(string $query): string
    {
        $query = trim(preg_replace('/\s+/', ' ', $query));

        return strlen($query) > 300 ? substr($query, 0, 300) . ' ...' : $query;
    }
}
