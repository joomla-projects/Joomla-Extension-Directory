<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Checker;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Archive\Archive;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;
use RuntimeException;
use Throwable;

/**
 * Runs the JED Checker (com_jedchecker) rules against a listing's package, for the JED Checker
 * tab on the ticket screen.
 *
 * JED Checker is the tool the JED team reviews every submission with, and the rejection mails
 * (`com_jed.extension_jed_checker_errors`, the ER1/NM1/... codes) speak its vocabulary. JED 5.3
 * had the tab but not the call behind it - the `CheckerApi::check()` line was commented out and
 * the tab showed a static copy of some results. This is the call.
 *
 * JED Checker has no service API to depend on: its rules are a library of plain classes
 * (`libraries/rule.php`, `libraries/rules/<rule>.php`, one `JedcheckerRules<Rule>` class each with
 * a `check()` that fills a `JEDcheckerReport`), and its own controller runs them exactly the way
 * {@see self::check()} does. That library layout has been stable from JED Checker 2 through 4,
 * so it is what is loaded here - defensively, because it is somebody else's extension: a missing
 * install, a rule that throws, a report without a method all degrade to a message, never to a
 * broken ticket screen.
 *
 * Nothing here is public (4.9/8.7): the tab is team-only, and the package is read from the
 * JED's own copy or the developer's download link, never from the request.
 *
 * @since 4.0.0
 */
class JedCheckerService
{
    /**
     * Where JED Checker keeps its rule library.
     *
     * @since 4.0.0
     */
    public const CHECKER_PATH = JPATH_ADMINISTRATOR . '/components/com_jedchecker';

    /**
     * The most a package download is allowed to weigh. Packages are archives of source; a
     * listing whose "package" is bigger than this is not something a checker should read into
     * memory on a request.
     *
     * @since 4.0.0
     */
    public const MAX_PACKAGE_BYTES = 52428800;

    /**
     * Package extensions JED Checker can open.
     *
     * @since 4.0.0
     */
    private const PACKAGE_EXTENSIONS = ['zip', 'tar', 'gz', 'tgz', 'bz2', 'tbz2'];

    /**
     * @param DatabaseInterface $db            The database driver.
     * @param string            $workspaceRoot Where packages are unpacked while a check runs.
     *
     * @since 4.0.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $workspaceRoot = JPATH_ADMINISTRATOR . '/components/com_jed/audit-workspace'
    ) {
    }

    /**
     * Whether JED Checker is installed in a shape this service can drive.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function isAvailable(): bool
    {
        return is_file(self::CHECKER_PATH . '/libraries/rule.php')
            && is_file(self::CHECKER_PATH . '/libraries/report.php')
            && is_dir(self::CHECKER_PATH . '/libraries/rules');
    }

    /**
     * The package on record for a listing: what the ticket screen says it would check.
     *
     * The JED's own copy wins - an uploaded package is what the developer submitted for review,
     * and it cannot change under the reviewer. Only when there is none does the listing's
     * download link count, and then it is fetched fresh at check time.
     *
     * @param int $extensionId The listing.
     *
     * @return array{source: string, label: string, path: ?string, url: ?string}|null
     *         `source` is "upload" or "url"; null when the listing has neither.
     *
     * @since 4.0.0
     */
    public function describePackage(int $extensionId): ?array
    {
        $db = $this->db;

        $upload = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['file', 'originalFile']))
                ->from($db->quoteName('#__jed_extensions_files'))
                ->where($db->quoteName('extension_id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
                ->order($db->quoteName('id') . ' DESC')
        )->loadObject();

        if ($upload && $upload->file !== '') {
            $path = Path::clean(JPATH_ROOT . '/' . ltrim((string) $upload->file, '/\\'));

            if (is_file($path)) {
                return [
                    'source' => 'upload',
                    'label'  => (string) ($upload->originalFile ?: basename($path)),
                    'path'   => $path,
                    'url'    => null,
                ];
            }
        }

        $listing = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['internal_download_url', 'download_url']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadObject();

        foreach (['internal_download_url', 'download_url'] as $column) {
            $url = trim((string) ($listing->$column ?? ''));

            if ($url !== '' && preg_match('#^https?://#i', $url)) {
                return ['source' => 'url', 'label' => $url, 'path' => null, 'url' => $url];
            }
        }

        return null;
    }

    /**
     * Run every JED Checker rule against the listing's package.
     *
     * @param int $extensionId The listing.
     *
     * @return array{
     *     checked: string,
     *     package: string,
     *     rules: array<int, array{id: string, title: string, description: string, errors: int, warnings: int, compat: int, notices: int, infos: int, html: string}>,
     *     totals: array{errors: int, warnings: int, compat: int, notices: int, infos: int}
     * }
     *
     * @throws RuntimeException When JED Checker is not installed, the listing has no package, or
     *                          the package cannot be fetched or unpacked. The message is shown to
     *                          the team as it is.
     *
     * @since 4.0.0
     */
    public function check(int $extensionId): array
    {
        if (!$this->isAvailable()) {
            throw new RuntimeException(Text::_('COM_JED_JEDCHECKER_NOT_INSTALLED'));
        }

        $package = $this->describePackage($extensionId);

        if ($package === null) {
            throw new RuntimeException(Text::_('COM_JED_JEDCHECKER_NO_PACKAGE'));
        }

        $workspace = rtrim($this->workspaceRoot, '/\\') . '/jedchecker-' . $extensionId . '-' . bin2hex(random_bytes(4));
        $unpacked  = $workspace . '/unpacked';

        if (!Folder::create($unpacked)) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_WORKSPACE_FAILED', $workspace));
        }

        try {
            $archive = $package['path'] ?? $this->download((string) $package['url'], $workspace);

            $this->unpack($archive, $unpacked);

            $rules  = $this->runRules($unpacked);
            $totals = ['errors' => 0, 'warnings' => 0, 'compat' => 0, 'notices' => 0, 'infos' => 0];

            foreach ($rules as $rule) {
                foreach ($totals as $key => $count) {
                    $totals[$key] = $count + $rule[$key];
                }
            }

            return [
                'checked' => Factory::getDate()->toSql(),
                'package' => $package['label'],
                'rules'   => $rules,
                'totals'  => $totals,
            ];
        } finally {
            // The unpacked source is the developer's code and stays nowhere the JED did not
            // already keep it. The download, if there was one, goes with it.
            try {
                Folder::delete($workspace);
            } catch (Throwable) {
                // A workspace that would not delete is a nuisance, not a failed check.
            }
        }
    }

    /**
     * Fetch the package from the listing's download link.
     *
     * @param string $url       The link.
     * @param string $workspace Where to put it.
     *
     * @return string The downloaded file.
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function download(string $url, string $workspace): string
    {
        try {
            $response = (new HttpFactory())->getHttp()->get($url, ['User-Agent' => 'JoomlaExtensionsDirectory/4.1 (+https://extensions.joomla.org/; JED Checker)'], 60);
        } catch (Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_DOWNLOAD_FAILED', $url, $e->getMessage()));
        }

        $status = $response->getStatusCode();

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_DOWNLOAD_FAILED', $url, 'HTTP ' . $status));
        }

        $body = (string) $response->getBody();

        if (\strlen($body) > self::MAX_PACKAGE_BYTES) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_PACKAGE_TOO_LARGE', $url));
        }

        $extension = strtolower(pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (!\in_array($extension, self::PACKAGE_EXTENSIONS, true)) {
            $extension = 'zip';
        }

        $target = $workspace . '/package.' . $extension;

        if (!File::write($target, $body)) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_WORKSPACE_FAILED', $workspace));
        }

        return $target;
    }

    /**
     * Unpack the package, and any package inside it - a "package" type extension is an archive of
     * archives, and JED Checker's own controller unpacks those the same way.
     *
     * @param string $archive  The package file.
     * @param string $into     The directory to unpack into.
     *
     * @return void
     *
     * @throws RuntimeException
     *
     * @since 4.0.0
     */
    private function unpack(string $archive, string $into): void
    {
        try {
            (new Archive(['tmp_path' => Factory::getApplication()->get('tmp_path')]))->extract($archive, $into);
        } catch (Throwable $e) {
            throw new RuntimeException(Text::sprintf('COM_JED_JEDCHECKER_UNPACK_FAILED', $e->getMessage()));
        }

        foreach (Folder::files($into, '\.(zip|tar|gz|tgz|bz2|tbz2)$', true, true) as $inner) {
            $innerDir = $inner . '.unpacked';

            try {
                (new Archive(['tmp_path' => Factory::getApplication()->get('tmp_path')]))->extract($inner, $innerDir);
                File::delete($inner);
            } catch (Throwable) {
                // An inner archive that will not open is something the rules will report on in
                // their own words; it is not a reason to stop.
            }
        }
    }

    /**
     * Load JED Checker's rule library and run every rule.
     *
     * @param string $basedir The unpacked package.
     *
     * @return array<int, array{id: string, title: string, description: string, errors: int, warnings: int, compat: int, notices: int, infos: int, html: string}>
     *
     * @since 4.0.0
     */
    private function runRules(string $basedir): array
    {
        // The rules' titles and messages are JED Checker's own strings.
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_jedchecker', JPATH_ADMINISTRATOR)
            || $language->load('com_jedchecker', self::CHECKER_PATH);

        require_once self::CHECKER_PATH . '/libraries/report.php';
        require_once self::CHECKER_PATH . '/libraries/rule.php';

        $params  = ComponentHelper::getParams('com_jedchecker');
        $results = [];

        foreach (Folder::files(self::CHECKER_PATH . '/libraries/rules', '\.php$') as $file) {
            $name  = substr($file, 0, -4);
            $class = 'JedcheckerRules' . ucfirst($name);

            try {
                if (!class_exists($class)) {
                    require_once self::CHECKER_PATH . '/libraries/rules/' . $file;
                }

                if (!class_exists($class)) {
                    continue;
                }

                // basedir in the options too: the report strips it from the paths it prints,
                // and it only learns it from the options the rule hands down to it.
                $rule          = new $class(['params' => $params, 'basedir' => $basedir]);
                $rule->params  = $params;
                $rule->basedir = $basedir;

                $rule->check();

                $report = $this->property($rule, 'report');

                $results[] = [
                    'id'          => (string) ($this->property($rule, 'id') ?? strtoupper($name)),
                    'title'       => Text::_((string) ($this->property($rule, 'title') ?? $name)),
                    'description' => Text::_((string) ($this->property($rule, 'description') ?? '')),
                    'errors'      => $this->count($report, 'getErrorCount'),
                    'warnings'    => $this->count($report, 'getWarningCount'),
                    'compat'      => $this->count($report, 'getCompatCount'),
                    'notices'     => $this->count($report, 'getNoticeCount'),
                    'infos'       => $this->count($report, 'getInfoCount'),
                    'html'        => $this->html($report),
                    'ordering'    => (int) ($this->property($rule, 'ordering') ?? 0),
                ];
            } catch (Throwable $e) {
                // One rule that cannot cope with a package is a fact about that rule, reported as
                // such, and the other rules still run.
                $results[] = [
                    'id'          => strtoupper($name),
                    'title'       => $name,
                    'description' => '',
                    'errors'      => 0,
                    'warnings'    => 0,
                    'compat'      => 0,
                    'notices'     => 0,
                    'infos'       => 0,
                    'html'        => '<div class="alert alert-secondary">'
                        . htmlspecialchars(Text::sprintf('COM_JED_JEDCHECKER_RULE_FAILED', $e->getMessage()), ENT_QUOTES, 'UTF-8')
                        . '</div>',
                    'ordering' => 0,
                ];
            }
        }

        usort($results, static fn (array $a, array $b) => [$a['ordering'], $a['title']] <=> [$b['ordering'], $b['title']]);

        return array_map(static function (array $rule): array {
            unset($rule['ordering']);

            return $rule;
        }, $results);
    }

    /**
     * Read a property off a JED Checker object, whichever way that version exposes it.
     *
     * @param object $object   The rule or report.
     * @param string $property The property.
     *
     * @return mixed
     *
     * @since 4.0.0
     */
    private function property(object $object, string $property): mixed
    {
        if (method_exists($object, 'get')) {
            $value = $object->get($property);

            if ($value !== null) {
                return $value;
            }
        }

        $getter = 'get' . ucfirst($property);

        if (method_exists($object, $getter)) {
            return $object->$getter();
        }

        return $object->$property ?? null;
    }

    /**
     * A count off the report, or 0 when the report has no such count.
     *
     * @param object|null $report The report.
     * @param string      $method The counting method.
     *
     * @return int
     *
     * @since 4.0.0
     */
    private function count(?object $report, string $method): int
    {
        return $report !== null && method_exists($report, $method) ? (int) $report->$method() : 0;
    }

    /**
     * The report's rendered findings.
     *
     * @param object|null $report The report.
     *
     * @return string
     *
     * @since 4.0.0
     */
    private function html(?object $report): string
    {
        if ($report === null) {
            return '';
        }

        foreach (['getHtml', 'getHTML'] as $method) {
            if (method_exists($report, $method)) {
                return (string) $report->$method();
            }
        }

        return '';
    }
}
