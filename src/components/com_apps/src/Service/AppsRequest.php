<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * One call of the Install from Web client, reduced to what changes the answer.
 *
 * The client also sends product, dev_level, lang and list. They do not change the response (it is
 * English only, the grid/list switch is done in the browser), so they are not part of the
 * request and not part of the cache key: fewer distinct keys means more cache hits.
 *
 * @since 4.0.0
 */
final class AppsRequest
{
    public const VIEWS = ['dashboard', 'category', 'extension'];

    /** ordering value sent by the client => SQL ORDER BY */
    public const ORDERINGS = [
        ''                   => 'a.popular DESC, a.score_overall DESC, a.score_count DESC, a.name ASC',
        'num_reviews'        => 'a.score_count DESC, a.name ASC',
        'score'              => 'a.score_overall DESC, a.score_count DESC, a.name ASC',
        'core_title'         => 'a.name ASC',
        'core_created_time'  => 'a.created DESC, a.name ASC',
        'core_modified_time' => 'a.modified DESC, a.name ASC',
    ];

    public const SEARCH_MAX_LENGTH = 64;

    /** Joomla 4 is the first release whose client takes the Bootstrap 5 markup; older ones, and unknown ones, get the old. */
    public const FIRST_BOOTSTRAP_RELEASE = 4;

    /** Newest client plugin (answer 1) and oldest one that still works (answer 0); older ones are told to update. */
    public const PLUGIN_LATEST = '2.1.2';
    public const PLUGIN_WORKS  = '1.0.5';

    /** The fixed name Joomla 3's plugin uses, and what jQuery generates when none is set. */
    public const CALLBACK_PATTERN = '/^(jedapps_jsonpcallback|jQuery[0-9_]{10,60})$/';

    private function __construct(
        public readonly string $view,
        public readonly int $id,
        public readonly string $search,
        public readonly string $ordering,
        public readonly ?int $major,
        public readonly bool $legacy = false,
        public readonly string $callback = '',
        public readonly bool $allVersions = false,
        public readonly bool $searchTooShort = false,
        public readonly int $pluginStatus = -1
    ) {
    }

    /**
     * @param array $query         The request parameters, as strings.
     * @param int   $searchMinimum Shortest accepted search term.
     *
     * @return self
     *
     * @throws InvalidRequestException
     * @since  4.0.0
     */
    public static function fromQuery(array $query, int $searchMinimum = 2): self
    {
        $view = strtolower(self::scalar($query, 'view') ?: 'dashboard');

        if (!in_array($view, self::VIEWS, true)) {
            throw new InvalidRequestException('COM_APPS_ERROR_VIEW');
        }

        $rawId = self::scalar($query, 'id');

        if ($rawId !== '' && !ctype_digit($rawId)) {
            throw new InvalidRequestException('COM_APPS_ERROR_ID');
        }

        $id = (int) $rawId;

        // A search is always a dashboard request: the client keeps the id of the category it was
        // in, and the directory has never limited a search to it.
        $search = self::normaliseSearch(self::scalar($query, 'filter_search'));

        if ($search !== '') {
            $view = 'dashboard';
            $id   = 0;
        }

        if (($view === 'category' || $view === 'extension') && $id < 1) {
            throw new InvalidRequestException('COM_APPS_ERROR_ID');
        }

        if ($view === 'dashboard') {
            $id = 0;
        }

        $ordering = self::scalar($query, 'ordering');

        if (!array_key_exists($ordering, self::ORDERINGS)) {
            $ordering = '';
        }

        $release = self::releaseMajor(self::scalar($query, 'release'));

        return new self(
            $view,
            $id,
            $search,
            $ordering,
            self::major($release, self::scalar($query, 'filter_version')),
            $release === null || $release < self::FIRST_BOOTSTRAP_RELEASE,
            self::callback(self::scalar($query, 'callback')),
            strtolower(self::scalar($query, 'filter_version')) === 'all',
            $search !== '' && mb_strlen($search) < $searchMinimum,
            self::pluginStatus(self::scalar($query, 'pv'))
        );
    }

    /**
     * Search terms as the client writes them (lower case, `_` for a space), as plain words.
     *
     * @since 4.0.0
     */
    public static function normaliseSearch(string $raw): string
    {
        $raw = mb_strtolower(rawurldecode($raw));
        $raw = preg_replace('/[^\p{L}\p{N}\- ]+/u', ' ', str_replace('_', ' ', $raw)) ?? '';
        $raw = trim((string) preg_replace('/\s+/u', ' ', $raw));

        return trim(mb_substr($raw, 0, self::SEARCH_MAX_LENGTH));
    }

    /**
     * 1 when the client plugin version (pv, base64 or plain) is current, 0 when it works but is
     * old, -1 otherwise. Joomla 3's plugin shows an update notice for anything below 1.
     *
     * @since 4.0.0
     */
    public static function pluginStatus(string $pv): int
    {
        $decoded = base64_decode($pv, true);
        $version = preg_replace('/[^\d.]/', '', $decoded !== false && $decoded !== '' ? $decoded : $pv) ?? '';

        if ($version === '') {
            return -1;
        }

        if (version_compare($version, self::PLUGIN_LATEST, '>=')) {
            return 1;
        }

        return version_compare($version, self::PLUGIN_WORKS, '>=') ? 0 : -1;
    }

    /**
     * The client's Joomla major version, from the release parameter.
     *
     * The plugin sends it base64 encoded ("Ni4y" is 6.2). A plain "6.2" is read too, which only
     * matters to someone calling the endpoint by hand. Anything else is not a release.
     *
     * @since 4.0.0
     */
    private static function releaseMajor(string $release): ?int
    {
        if ($release === '') {
            return null;
        }

        $plain = preg_match('/^\d{1,2}\.\d+/', $release) === 1 ? $release : (string) base64_decode($release, true);

        return preg_match('/^(\d{1,2})\.\d+/', $plain, $m) === 1 && (int) $m[1] > 0 ? (int) $m[1] : null;
    }

    /**
     * The Joomla major version to filter on, or null for every version.
     *
     * "current" (and anything unknown) means the major version the client runs on.
     *
     * @since 4.0.0
     */
    private static function major(?int $release, string $filterVersion): ?int
    {
        $filterVersion = strtolower($filterVersion);

        if ($filterVersion === 'all') {
            return null;
        }

        if (ctype_digit($filterVersion) && (int) $filterVersion > 0 && (int) $filterVersion < 100) {
            return (int) $filterVersion;
        }

        return $release;
    }

    /**
     * The JSONP callback of Joomla 3's client. Only the names its scripts use are accepted, so the
     * endpoint cannot be made to call an arbitrary function of the page that includes it.
     *
     * @throws InvalidRequestException
     */
    private static function callback(string $callback): string
    {
        if ($callback === '') {
            return '';
        }

        if (preg_match(self::CALLBACK_PATTERN, $callback) !== 1) {
            throw new InvalidRequestException('COM_APPS_ERROR_CALLBACK');
        }

        return $callback;
    }

    /**
     * The key a response is cached under.
     *
     * @since 4.0.0
     */
    public function cacheKey(): string
    {
        return sprintf(
            '%s-%d-%s-%s-%s-%s-p%d',
            $this->view,
            $this->id,
            md5($this->search),
            $this->ordering === '' ? 'default' : $this->ordering,
            $this->major === null ? 'all' : (string) $this->major,
            $this->legacy ? 'legacy' : 'current',
            $this->pluginStatus + 1
        );
    }

    /**
     * @since 4.0.0
     */
    public function isSearch(): bool
    {
        return $this->search !== '';
    }

    private static function scalar(array $query, string $name): string
    {
        $value = $query[$name] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
