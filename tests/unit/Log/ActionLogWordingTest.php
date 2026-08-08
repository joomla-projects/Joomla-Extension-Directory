<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Log;

use Jed\Component\Jed\Administrator\Log\JedActionLog;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * That every administrative decision `P1-22` records can actually be read.
 *
 * The acceptance criterion is "log entries are readable in the core backend list without decoding
 * ids", and the way that breaks is not dramatic: somebody adds an action, emits it from a model,
 * and never writes the wording. Core then renders the bare language key, and because the entry is
 * still *there* nobody notices until the day somebody needs to look something up.
 *
 * The three artefacts have to agree, and nothing but a test makes them:
 *
 *  - {@see JedActionLog}'s constants - what the components may raise;
 *  - `plg_actionlog_jed`'s message map - which key each action renders with;
 *  - `plg_actionlog_jed.ini` - the sentence itself.
 *
 * @since 4.1.0
 */
final class ActionLogWordingTest extends TestCase
{
    /**
     * Where the plugin lives, relative to this file.
     *
     * @since 4.1.0
     */
    private const PLUGIN = __DIR__ . '/../../../src/plugins/actionlog/jed';

    /**
     * Every action a component may raise, taken from the constants rather than from a list kept
     * here - a list kept here would need the same discipline it is meant to enforce.
     *
     * @return array<string, array{0: string}>
     *
     * @since 4.1.0
     */
    public static function actions(): array
    {
        $cases = [];

        foreach ((new ReflectionClass(JedActionLog::class))->getConstants() as $name => $value) {
            if ($name === 'EVENT') {
                continue;
            }

            $cases[$value] = [$value];
        }

        return $cases;
    }

    /**
     * The plugin's action => language key map, read out of the source.
     *
     * The plugin class is not loadable here: it extends `ActionLogPlugin`, which lives in
     * com_actionlogs and only exists on a deployed site. The map is a plain literal, so parsing
     * it is exact rather than approximate.
     *
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    private static function messageMap(): array
    {
        $source = file_get_contents(self::PLUGIN . '/src/Extension/Jed.php');

        preg_match('/private const MESSAGES = \[(.*?)\];/s', (string) $source, $block);
        preg_match_all("/'([a-z.]+)'\s*=>\s*'([A-Z_]+)'/", $block[1] ?? '', $pairs, PREG_SET_ORDER);

        $map = [];

        foreach ($pairs as $pair) {
            $map[$pair[1]] = $pair[2];
        }

        return $map;
    }

    /**
     * The keys the language file actually defines.
     *
     * @return array<string, string>
     *
     * @since 4.1.0
     */
    private static function strings(): array
    {
        return (array) parse_ini_file(self::PLUGIN . '/language/en-GB/plg_actionlog_jed.ini');
    }

    /**
     * @return void
     *
     * @since 4.1.0
     */
    public function testTheMapAndTheLanguageFileWereBothFound(): void
    {
        // Without this the two data providers below would pass vacuously if a path ever moved.
        $this->assertNotEmpty(self::messageMap(), 'The plugin message map could not be read.');
        $this->assertNotEmpty(self::strings(), 'The plugin language file could not be read.');
    }

    /**
     * @param string $action The action constant's value.
     *
     * @return void
     *
     * @dataProvider actions
     *
     * @since 4.1.0
     */
    public function testEveryActionHasWording(string $action): void
    {
        $map = self::messageMap();

        $this->assertArrayHasKey(
            $action,
            $map,
            \sprintf('"%s" is raised by the components but plg_actionlog_jed does not map it.', $action)
        );

        $this->assertArrayHasKey(
            $map[$action],
            self::strings(),
            \sprintf('"%s" maps to %s, which the language file does not define.', $action, $map[$action])
        );
    }

    /**
     * @return void
     *
     * @since 4.1.0
     */
    public function testTheMapHasNothingSpare(): void
    {
        $known = array_values(array_diff(
            (new ReflectionClass(JedActionLog::class))->getConstants(),
            [JedActionLog::EVENT]
        ));

        foreach (array_keys(self::messageMap()) as $action) {
            $this->assertContains(
                $action,
                $known,
                \sprintf('plg_actionlog_jed maps "%s", which no component can raise.', $action)
            );
        }
    }

    /**
     * Every `{placeholder}` in a message has to be something a call site supplies, or the backend
     * list renders the braces verbatim.
     *
     * `username` and `accountlink` are core's - `ActionLogPlugin::addLog()` fills them in from the
     * acting identity - and `itemlink` is the plugin's, built from the context. The rest are the
     * emitter's business and are checked by name against what the emitting code passes.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testPlaceholdersAreSupplied(): void
    {
        $supplied = array_merge(
            ['username', 'accountlink', 'itemlink', 'id'],
            // Everything any call site puts into $data. Kept explicit: the point is to notice when
            // a message starts asking for something nobody sends.
            [
                'title', 'reason', 'extension',
                'from', 'fromlink', 'to', 'tolink',
                'maintainer', 'maintainerlink',
                'assignee', 'assigneelink', 'status',
                'period', 'scope', 'changes',
                'field', 'detail',
            ]
        );

        foreach (self::strings() as $key => $message) {
            preg_match_all('/\{([a-z_]+)\}/', (string) $message, $found);

            foreach ($found[1] as $placeholder) {
                $this->assertContains(
                    $placeholder,
                    $supplied,
                    \sprintf('%s wants {%s}, which no call site supplies.', $key, $placeholder)
                );
            }
        }
    }
}
