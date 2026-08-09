<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

/**
 * The JED3 migration step plan (`P1-24`), asserted against the plugin that implements it.
 *
 * The sample-data API dispatches each step by calling a method named after its number, so the plan
 * and the methods have to agree exactly - and neither of them announces a disagreement. A step
 * whose method is missing is simply never run: the progress bar advances, the run reports success,
 * and one data set is quietly absent from the result. That is the failure mode this file exists
 * for, and it is why the methods are checked against the plan rather than counted.
 *
 * The ordering assertions are the other half. Three of the steps are only correct in one position:
 *
 *   - the status history has to be written BEFORE the baseline revision, because the baseline
 *     appends each listing's current state as its one active revision and both
 *     `ExtensionformModel::getItem()` and the admin list resolve "the current version" with
 *     MAX(id) rather than with `active = 1`. A status revision written afterwards becomes what the
 *     developer edits.
 *   - the linked extensions have to be written AFTER it, because they are copied onto that
 *     baseline revision and it does not exist until the baseline has run.
 *   - the cleanup has to be last, because it drops the staging tables the others read.
 *
 * All three are invisible in the plan itself - it is an ordered array of file names - so they are
 * asserted here instead of left to whoever next inserts a step.
 *
 * @since 4.0.0
 */
final class StepPlanTest extends TestCase
{
    /**
     * The plugin directory.
     *
     * @since 4.0.0
     */
    private const PLUGIN = __DIR__ . '/../../../src/plugins/sampledata/jed_migrate';

    /**
     * The step plan, as file names in step order, keyed by step number.
     *
     * Read out of the plugin rather than by calling stepPlan(): the class extends CMSPlugin and
     * its constructor reaches into the Joomla container for a database, which a unit test has no
     * business booting. The plan is a pure function of three constants and the source is the only
     * thing that has to be right, so it is reproduced from those constants here and the
     * reproduction is checked against the source in testPlanMatchesTheConstants().
     *
     * @return array<int, string>
     *
     * @since 4.0.0
     */
    private function plan(): array
    {
        $plan = [];

        for ($i = 1; $i <= 10; $i++) {
            $plan[] = 'step' . $i . '.sql';
        }

        $plan[] = 'history_prepare.sql';

        for ($i = 0; $i < $this->constant('HISTORY_BATCHES'); $i++) {
            $plan[] = 'history_batch.sql';
        }

        $plan[] = 'status_prepare.sql';

        for ($i = 0; $i < $this->constant('STATUS_BATCHES'); $i++) {
            $plan[] = 'status_batch.sql';
        }

        $plan[] = 'history_baseline.sql';
        $plan[] = 'hits.sql';
        $plan[] = 'rsforms.sql';
        $plan[] = 'tags_vocab.sql';

        for ($i = 0; $i < $this->constant('TAG_UCM_BATCHES'); $i++) {
            $plan[] = 'tags_ucm_batch.sql';
        }

        $plan[] = 'tags_map.sql';
        $plan[] = 'abandonware.sql';
        $plan[] = 'linked.sql';
        $plan[] = 'useraccess.sql';
        $plan[] = 'tickets.sql';
        $plan[] = 'cleanup.sql';

        // Step numbers are 1 based, because that is what the sample-data API dispatches on.
        return array_combine(range(1, \count($plan)), $plan);
    }

    /**
     * Read one of the plugin's private constants out of its source.
     *
     * @param string $name The constant name.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function constant(string $name): int
    {
        $source = $this->source();

        $this->assertSame(
            1,
            preg_match('/private const ' . preg_quote($name, '/') . '\s*=\s*(\d+);/', $source, $m),
            $name . ' is not declared as a plain integer constant in jed_migrate.php.'
        );

        return (int) $m[1];
    }

    /**
     * The plugin source.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function source(): string
    {
        $file = self::PLUGIN . '/jed_migrate.php';

        $this->assertFileExists($file);

        return (string) file_get_contents($file);
    }

    /**
     * Every step in the plan has the SQL file it names.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryStepHasItsSqlFile(): void
    {
        foreach (array_unique($this->plan()) as $file) {
            $this->assertFileExists(self::PLUGIN . '/sql/' . $file);
        }
    }

    /**
     * Every step number in the plan has the dispatch method the sample-data API will call, and
     * there is no method for a step the plan does not have.
     *
     * The second half matters as much as the first: a leftover method for a step that no longer
     * exists makes the module request a step the plugin answers with "missing file", which reads
     * like a broken installation rather than like a stale method.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryStepHasItsDispatchMethod(): void
    {
        $source = $this->source();

        preg_match_all('/public function onAjaxSampledataApplyStep(\d+)\(\)/', $source, $matches);

        $declared = array_map('intval', $matches[1]);
        $expected = array_keys($this->plan());

        sort($declared);
        sort($expected);

        $this->assertSame(
            $expected,
            $declared,
            'The onAjaxSampledataApplyStepN methods do not match the steps the plan defines.'
        );
    }

    /**
     * Each dispatch method applies its own step number.
     *
     * A copy-paste slip here - onAjaxSampledataApplyStep41() calling applyStep(40) - runs one step
     * twice and skips another, and every one of them still reports success.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEachDispatchMethodAppliesItsOwnStep(): void
    {
        $source = $this->source();

        preg_match_all(
            '/public function onAjaxSampledataApplyStep(\d+)\(\)\s*\{\s*return \$this->applyStep\((\d+)\);/',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        $this->assertCount(
            \count($this->plan()),
            $matches,
            'Not every dispatch method is a plain "return $this->applyStep(N);".'
        );

        foreach ($matches as $match) {
            $this->assertSame($match[1], $match[2], 'onAjaxSampledataApplyStep' . $match[1] . '() applies a different step.');
        }
    }

    /**
     * STEP_COUNT is what the plan actually contains.
     *
     * It is the number the sample-data module draws its progress bar from, so a plan longer than
     * the count leaves the last steps unrun.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testStepCountMatchesThePlan(): void
    {
        $source = $this->source();

        $this->assertSame(
            1,
            preg_match('/private const STEP_COUNT = (\d+) \+ self::HISTORY_BATCHES \+ self::STATUS_BATCHES \+ self::TAG_UCM_BATCHES;/', $source, $m),
            'STEP_COUNT is no longer declared as a fixed count plus the three batch constants.'
        );

        $expected = (int) $m[1] + $this->constant('HISTORY_BATCHES') + $this->constant('STATUS_BATCHES') + $this->constant('TAG_UCM_BATCHES');

        $this->assertSame(\count($this->plan()), $expected, 'STEP_COUNT does not match the number of steps in the plan.');
    }

    /**
     * The plan reproduced above is the plan the plugin builds: same files, same order.
     *
     * Compared as the order the file names appear in stepPlan(), which is what the method's
     * structure guarantees - the batch loops each name one file, so a plan of 68 steps is built
     * from 22 distinct file names in a fixed sequence.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testPlanMatchesTheConstants(): void
    {
        $source = $this->source();

        $start = strpos($source, 'private function stepPlan(): array');
        $end   = strpos($source, 'private function applyStep(');

        $this->assertIsInt($start);
        $this->assertIsInt($end);

        // The closing quote has to be followed by a comma or a bracket. Without that the pattern
        // also matches the 'step' of "'file' => 'step' . $i . '.sql'", which is a fragment of a
        // name rather than a name.
        preg_match_all("/'file'\s*=>\s*'([a-z0-9_.]+)'\s*[,\]]/", substr($source, $start, $end - $start), $matches);

        // step1.sql .. step10.sql are built by a loop and appear once as a concatenation, so the
        // literal list starts after them.
        $expected = array_values(array_unique(array_slice($this->plan(), 10, null, true)));

        $this->assertSame($expected, $matches[1], 'stepPlan() names its SQL files in a different order than the plan.');
    }

    /**
     * The three positions that are not free.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testTheOrderingInvariantsHold(): void
    {
        $plan = $this->plan();

        $positionOf = static function (string $file) use ($plan): int {
            $found = array_search($file, $plan, true);

            return $found === false ? -1 : (int) $found;
        };

        $lastStatusBatch = max(array_keys($plan, 'status_batch.sql', true));
        $baseline        = $positionOf('history_baseline.sql');

        $this->assertGreaterThan(0, $lastStatusBatch);
        $this->assertGreaterThan(
            $lastStatusBatch,
            $baseline,
            'The active baseline revision must be written after the status history, or it no longer carries the highest id per listing.'
        );

        $this->assertGreaterThan(
            $baseline,
            $positionOf('linked.sql'),
            'The linked extensions are copied onto the active baseline revision, so they must be written after it exists.'
        );

        $this->assertSame(
            array_key_last($plan),
            $positionOf('cleanup.sql'),
            'The cleanup drops the staging tables every other step reads, so it must be last.'
        );
    }

    /**
     * Every label the plan uses exists in the plugin's language file.
     *
     * A missing key does not fail the step - Joomla renders the key itself - so the run reports
     * PLG_SAMPLEDATA_JED_MIGRATE_TICKETS_SUCCESS at the user and carries on.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testEveryLabelIsTranslated(): void
    {
        $source = $this->source();
        $ini    = (string) file_get_contents(self::PLUGIN . '/language/en-GB/en-GB.plg_sampledata_jed_migrate.ini');

        // Same as in testPlanMatchesTheConstants: the trailing comma or bracket keeps the pattern
        // off the 'PLG_SAMPLEDATA_JED_MIGRATE_STEP' of the concatenated numbered-step labels,
        // which are checked separately below.
        preg_match_all("/'label'\s*=>\s*'([A-Z0-9_]+)'\s*[,\]]/", $source, $matches);

        $labels = array_unique($matches[1]);

        $this->assertNotEmpty($labels);

        foreach ($labels as $label) {
            $this->assertMatchesRegularExpression(
                '/^' . preg_quote($label, '/') . '="/m',
                $ini,
                $label . ' has no entry in the plugin language file.'
            );
        }

        // The ten numbered steps are named by concatenation, so they are checked separately.
        for ($i = 1; $i <= 10; $i++) {
            $this->assertMatchesRegularExpression(
                '/^PLG_SAMPLEDATA_JED_MIGRATE_STEP' . $i . '_SUCCESS="/m',
                $ini,
                'PLG_SAMPLEDATA_JED_MIGRATE_STEP' . $i . '_SUCCESS has no entry in the plugin language file.'
            );
        }
    }

    /**
     * The cleanup drops every staging table the steps create.
     *
     * A staging table left behind is not an error either: the next run's CREATE TABLE finds it,
     * the DROP IF EXISTS in front of it hides the fact, and in the meantime the site carries a
     * table full of legacy personal data that nothing owns.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function testCleanupDropsEveryStagingTable(): void
    {
        $created = [];
        $dropped = [];

        foreach (array_unique($this->plan()) as $file) {
            $sql = (string) file_get_contents(self::PLUGIN . '/sql/' . $file);

            // Strip comments first - these files explain themselves at length, and the prose names
            // the staging tables often enough to make an uncommented scan meaningless.
            $sql = preg_replace('/^--.*$/m', '', $sql);

            preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?(combine_[a-z0-9_]+)/i', $sql, $c);
            preg_match_all('/DROP TABLE IF EXISTS (combine_[a-z0-9_]+)/i', $sql, $d);

            $created = array_merge($created, $c[1]);

            if ($file === 'cleanup.sql') {
                $dropped = array_merge($dropped, $d[1]);
            }
        }

        // combine_jed_extensions outlives the run on purpose - cleanup.sql says why - and
        // combine_jed_rsp is dropped by the step that creates it.
        $survives = ['combine_jed_extensions', 'combine_jed_rsp'];
        $expected = array_values(array_diff(array_unique($created), $survives));

        sort($expected);
        $dropped = array_unique($dropped);
        sort($dropped);

        $this->assertSame([], array_values(array_diff($expected, $dropped)), 'cleanup.sql does not drop every staging table the migration creates.');
    }
}
