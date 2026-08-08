<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use RuntimeException;
use Throwable;

/**
 * Everything that has to be true before a URL is fetched on somebody's behalf, and everything
 * that happens to the answer afterwards.
 *
 * The validators do the checking; this decides *whether* to check. The endpoint is the only way
 * into it from a request, and it is an attractive one: an authenticated user can make the JED's
 * server issue an HTTP request to an address of their choosing, which is a request amplifier and
 * an anonymiser at the same time. So four bounds, all of them here rather than in the controller,
 * because the controller is not the only caller (`P1-09` will run these on a schedule):
 *
 *  1. **the format rules first** - a URL that cannot be stored is never fetched, which also means
 *     layer 2 costs nothing while the developer is still typing;
 *  2. **the cache** - the same URL and validator inside the freshness window returns the stored
 *     answer, so a form with eight fields does not become eight requests per edit;
 *  3. **the rate limit** - a bound per user per window, counted from the rows this service
 *     writes, which is the same shape the transfer lookups use;
 *  4. **the record** - every answer is stored, because the JED team needs it during moderation
 *     and `P1-09` needs a baseline. Never public (4.9).
 *
 * Permission is checked by the caller, not here: the site controller and the admin controller
 * answer "may this person edit this listing" differently, and that question belongs where the
 * request is.
 *
 * @since 4.1.0
 */
class UrlCheckService
{
    /**
     * Checks one user may run per window, and the window in hours.
     *
     * Generous enough that filling in a form and re-checking a few fields never touches it, tight
     * enough that the endpoint is not a useful amplifier. The cache means a developer working
     * through one listing spends far fewer than this.
     *
     * @since 4.1.0
     */
    public const RATE_LIMIT  = 120;
    public const RATE_WINDOW = 1;

    /**
     * How long a stored answer is reused, in minutes.
     *
     * @since 4.1.0
     */
    public const CACHE_MINUTES = 10;

    /**
     * @param DatabaseInterface    $db       The database.
     * @param UrlValidatorRegistry $registry The validators, by key.
     *
     * @since 4.1.0
     */
    public function __construct(
        protected readonly DatabaseInterface $db,
        protected readonly UrlValidatorRegistry $registry
    ) {
    }

    /**
     * Run a check, subject to all four bounds.
     *
     * @param string               $url          The URL as typed.
     * @param string               $validatorKey Which check to run.
     * @param User                 $user         Who is asking.
     * @param array<string, mixed> $context      Listing context for the validator, plus
     *                                           `extension_id` and `field` for the record.
     *
     * @return UrlCheckResult
     *
     * @since 4.1.0
     */
    public function check(string $url, string $validatorKey, User $user, array $context = []): UrlCheckResult
    {
        $url = trim($url);

        // 1. Format first. This is also layer 3's rule, and the reason the two cannot disagree.
        $formatErrors = UrlFormat::check($url);

        if ($formatErrors !== []) {
            return UrlCheckResult::error('COM_JED_URLCHECK_FORMAT_' . strtoupper($formatErrors[0]));
        }

        if (!$this->registry->has($validatorKey)) {
            throw new RuntimeException(\sprintf('No URL validator registered for "%s".', $validatorKey));
        }

        // 2. A fresh answer for this URL and this check is reused whoever asked for it. The
        //    result is a property of the URL, not of the person looking at it.
        $cached = $this->findFresh($url, $validatorKey);

        if ($cached !== null) {
            return $cached;
        }

        // 3. Only now does this cost anybody a request.
        $this->assertWithinRateLimit((int) $user->id);

        try {
            $result = $this->registry->get($validatorKey)->validate($url, $context);
        } catch (Throwable $e) {
            // A validator that throws is a bug in the validator, not a verdict about the URL.
            // It must not surface as "your link is broken", and it must not be cached.
            $result = UrlCheckResult::notice('COM_JED_URLCHECK_FAILED_FAILED');
        }

        $this->record($url, $validatorKey, $result, (int) $user->id, $context);

        return $result;
    }

    /**
     * A stored answer for this URL and check, if one is still fresh.
     *
     * @param string $url          The URL.
     * @param string $validatorKey The check.
     *
     * @return UrlCheckResult|null
     *
     * @since 4.1.0
     */
    protected function findFresh(string $url, string $validatorKey): ?UrlCheckResult
    {
        $since = Factory::getDate('-' . self::CACHE_MINUTES . ' minutes')->toSql();
        $hash  = hash('sha256', $url);

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['state', 'message', 'http_status', 'detail']))
            ->from($this->db->quoteName('#__jed_url_checks'))
            ->where($this->db->quoteName('url_hash') . ' = :hash')
            ->where($this->db->quoteName('validator') . ' = :validator')
            ->where($this->db->quoteName('checked') . ' >= :since')
            ->bind(':hash', $hash)
            ->bind(':validator', $validatorKey)
            ->bind(':since', $since)
            ->order($this->db->quoteName('checked') . ' DESC');

        $row = $this->db->setQuery($query, 0, 1)->loadObject();

        if ($row === null) {
            return null;
        }

        return new UrlCheckResult(
            (string) $row->state,
            (string) $row->message,
            [],
            (int) $row->http_status,
            $row->detail !== null ? (string) $row->detail : null
        );
    }

    /**
     * Refuse once a user has run too many checks in the window.
     *
     * @param int $userId The user.
     *
     * @return void
     *
     * @throws RuntimeException When the limit is reached.
     *
     * @since 4.1.0
     */
    protected function assertWithinRateLimit(int $userId): void
    {
        $since = Factory::getDate('-' . self::RATE_WINDOW . ' hours')->toSql();

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jed_url_checks'))
            ->where($this->db->quoteName('checked_by') . ' = :user')
            ->where($this->db->quoteName('checked') . ' >= :since')
            ->bind(':user', $userId, ParameterType::INTEGER)
            ->bind(':since', $since);

        if ((int) $this->db->setQuery($query)->loadResult() >= self::RATE_LIMIT) {
            throw new RuntimeException('rate_limit');
        }
    }

    /**
     * Store the answer.
     *
     * @param string               $url          The URL.
     * @param string               $validatorKey The check.
     * @param UrlCheckResult       $result       The answer.
     * @param int                  $userId       Who asked.
     * @param array<string, mixed> $context      Wants `extension_id` and `field`.
     *
     * @return void
     *
     * @since 4.1.0
     */
    protected function record(string $url, string $validatorKey, UrlCheckResult $result, int $userId, array $context): void
    {
        $row = (object) [
            'extension_id' => !empty($context['extension_id']) ? (int) $context['extension_id'] : null,
            'field'        => mb_substr((string) ($context['field'] ?? ''), 0, 64),
            'validator'    => $validatorKey,
            'url'          => mb_substr($url, 0, 255),
            'url_hash'     => hash('sha256', $url),
            'state'        => $result->state,
            'http_status'  => $result->status,
            'message'      => mb_substr($result->message, 0, 255),
            'detail'       => $result->detail !== null ? mb_substr($result->detail, 0, 255) : null,
            'checked_by'   => $userId,
            'checked'      => Factory::getDate()->toSql(),
        ];

        try {
            $this->db->insertObject('#__jed_url_checks', $row);
        } catch (Throwable $e) {
            // Losing the record must not lose the answer the developer is waiting for.
        }
    }

    /**
     * The most recent stored answer per field for a listing, for the moderation view.
     *
     * @param int $extensionId The listing.
     *
     * @return array<string, object>  Keyed by field name.
     *
     * @since 4.1.0
     */
    public function latestForExtension(int $extensionId): array
    {
        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName(['field', 'validator', 'url', 'state', 'http_status', 'message', 'detail', 'checked']))
            ->from($this->db->quoteName('#__jed_url_checks'))
            ->where($this->db->quoteName('extension_id') . ' = :id')
            ->bind(':id', $extensionId, ParameterType::INTEGER)
            ->order($this->db->quoteName('checked') . ' ASC');

        $latest = [];

        foreach ($this->db->setQuery($query)->loadObjectList() ?: [] as $row) {
            // Ordered ascending, so the last write per field wins and the array ends up holding
            // the newest.
            $latest[(string) $row->field] = $row;
        }

        return $latest;
    }
}
