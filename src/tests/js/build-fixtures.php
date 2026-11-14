<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Writes the fixtures of the browser tests from the real code:
 *
 *   tests/js/fixtures/feedrun-page.json      the Feed Runs page as tmpl/feedruns/default.php renders
 *                                            it for a run that has just started, and the reports a
 *                                            CVE and an NVD run send back step by step
 *   tests/cypress/fixtures/feedrun-cna.json  the CVE run's reports, for the stubbed Cypress spec
 *
 * Run it again after changing the template, the runner or the language strings:
 *   php tests/js/build-fixtures.php          (JOOMLA_LIBRARIES as for the feed tests)
 */

namespace Joomla\CMS\Router {
    // The view helpers the template calls, as the Feed Runs page would have them.
    final class Route
    {
        public static function _($url, $xhtml = true)
        {
            return '/administrator/' . $url;
        }
    }
}

namespace Joomla\CMS\HTML {
    final class HTMLHelper
    {
        public static function _($key, ...$args)
        {
            return $key === 'form.token' ? '<input type="hidden" name="0123456789abcdef0123456789abcdef" value="1">' : (string) ($args[0] ?? '');
        }
    }
}

namespace {
    use Jed\Component\Vel\Administrator\Enum\FeedSource;
    use Jed\Component\Vel\Administrator\Feed\FeedRunner;
    use Jed\Component\Vel\Administrator\Service\VelService;
    use Jed\Tests\Feeds\Support\Cms;
    use Jed\Tests\Feeds\Support\FakeFeeds;
    use Jed\Tests\Feeds\Support\TestDatabase;

    require dirname(__DIR__) . '/feeds/bootstrap.php';

    if (VEL_FEED_TESTS_SKIP !== '') {
        fwrite(STDERR, VEL_FEED_TESTS_SKIP . "\n");

        exit(1);
    }

    Cms::reset(['nvd_api_key' => 'test-key']);
    Cms::$translate = true;
    FakeFeeds::reset();
    FakeFeeds::install();
    FakeFeeds::$log = FakeFeeds::deltaLog(time() - 120, 4, 3);
    FakeFeeds::$nvd = FakeFeeds::nvdResults(1100);

    $db = TestDatabase::create();

    $sequences = [];

    foreach ([FeedSource::CNA, FeedSource::NVD] as $source) {
        $runner = new FeedRunner($db, new VelService());
        $run    = $runner->start($source);
        $list   = [$runner->report($runner->load((int) $run->id))];

        do {
            $list[] = $report = $runner->step((int) $run->id, 0);
        } while (!$report['done']);

        $sequences[$source->value] = $list;
    }

    // The page for a CVE run that has just started.
    $runner = new FeedRunner($db, new VelService());
    $run    = $runner->start(FeedSource::CNA);

    $view = new class () {
        public array $items = [];
        public ?array $progress = null;
        public string $progressProblem = '';
        public array $can = ['feeds' => true];
        public object $pagination;

        public function __construct()
        {
            $this->pagination = new class () {
                public function getListFooter()
                {
                    return '';
                }
            };
        }

        public function escape($value)
        {
            return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }
    };

    $view->progress = $runner->report($runner->load((int) $run->id));
    $view->items    = $db->setQuery('SELECT * FROM jos_vel_feed_runs ORDER BY id DESC')->loadObjectList();

    ob_start();
    (function () {
        include JPATH_ADMINISTRATOR . '/components/com_vel/tmpl/feedruns/default.php';
    })->call($view);
    $html = ob_get_clean();

    $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    file_put_contents(__DIR__ . '/fixtures/feedrun-page.json', json_encode(['html' => $html, 'report' => $view->progress, 'runId' => (int) $run->id, 'sequences' => $sequences], $flags) . "\n");
    file_put_contents(dirname(__DIR__) . '/cypress/fixtures/feedrun-cna.json', json_encode($sequences['cna'], $flags) . "\n");

    echo 'Wrote tests/js/fixtures/feedrun-page.json (CVE run: ', \count($sequences['cna']), ' reports, NVD run: ', \count($sequences['nvd']), ") and tests/cypress/fixtures/feedrun-cna.json\n";
}
