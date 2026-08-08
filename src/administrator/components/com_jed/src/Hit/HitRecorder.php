<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Hit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Access\JedAccessHelper;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Writes one row into `#__jed_hit_log`, or decides not to.
 *
 * **Once per session and extension and type**, not once per request. A visitor who reloads a
 * listing three times, or comes back to it after following a link away, is one person interested
 * in one extension - counting that as three says something untrue, and the number exists to be
 * used for ranking. It also removes most of the write volume: JED3 counted per request and
 * accumulated 2,158,587 rows in a month.
 *
 * The session is the unit rather than the IP because an IP is a whole office, and because the
 * session is already there and costs no query.
 *
 * **Nothing here may break a page.** A listing must render whether or not its view could be
 * counted, so every path out of this class swallows its errors. A statistic is not worth a 500.
 *
 * @since 4.0.0
 */
class HitRecorder
{
    /**
     * Where the per-session record of what has already been counted lives.
     *
     * @since 4.0.0
     */
    private const SESSION_KEY = 'com_jed.hits.counted';

    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(protected readonly DatabaseInterface $db)
    {
    }

    /**
     * Record a hit, unless this session has already been counted for it.
     *
     * @param int     $extensionId The listing.
     * @param HitType $type        View or download click.
     *
     * @return bool  True when a row was written.
     *
     * @since 4.0.0
     */
    public function record(int $extensionId, HitType $type): bool
    {
        if ($extensionId <= 0) {
            return false;
        }

        try {
            $app     = Factory::getApplication();
            $session = $app->getSession();
            $key     = $extensionId . ':' . $type->value;
            $counted = (array) $session->get(self::SESSION_KEY, []);

            if (isset($counted[$key])) {
                return false;
            }

            $counted[$key] = true;
            $session->set(self::SESSION_KEY, $counted);

            $input     = $app->getInput();
            $userAgent = (string) $input->server->getString('HTTP_USER_AGENT', '');
            $referrer  = trim((string) $input->server->getString('HTTP_REFERER', ''));
            $ip        = (string) $input->server->getString('REMOTE_ADDR', '');
            $ipHash    = $this->hashAddress($ip);

            $isRobot = BotDetector::isRobot($userAgent);

            // The rate is only asked about when the hit is not already a self-declared crawler:
            // it costs an indexed query, and a Googlebot hit gains nothing from it.
            $suspicious = $isRobot || BotDetector::isSuspicious(
                $userAgent,
                $referrer !== '',
                $ip !== '' && JedAccessHelper::isSuspectAddress($ip),
                $ipHash === null ? 0 : $this->recentHits($ipHash)
            );

            $row = (object) [
                'extension_id' => $extensionId,
                'hit_type'     => $type->value,
                'hit_time'     => Factory::getDate()->toSql(),
                'ip_hash'      => $ipHash,
                'user_agent'   => mb_substr($userAgent, 0, 255) ?: null,
                'is_robot'     => $isRobot ? 1 : 0,
                'suspicious'   => $suspicious ? 1 : 0,
            ];

            $this->db->insertObject('#__jed_hit_log', $row);

            return true;
        } catch (Throwable $e) {
            // A page is worth more than a statistic.
            return false;
        }
    }

    /**
     * The salted hash of an address.
     *
     * Salted with the site secret, so the hashes are not reversible with a rainbow table over the
     * ~4 billion IPv4 addresses - an unsalted hash of an IP is an IP with extra steps.
     *
     * @param string $ip The address.
     *
     * @return string|null  32 raw bytes, or null when there was no usable address.
     *
     * @since 4.0.0
     */
    protected function hashAddress(string $ip): ?string
    {
        $ip = trim($ip);

        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) Factory::getApplication()->get('secret'), true);
    }

    /**
     * How many hits this address has produced in the rate window.
     *
     * @param string $ipHash The hashed address.
     *
     * @return int
     *
     * @since 4.0.0
     */
    protected function recentHits(string $ipHash): int
    {
        $since = Factory::getDate('-' . BotDetector::RATE_MINUTES . ' minutes')->toSql();

        $query = $this->db->getQuery(true)
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__jed_hit_log'))
            ->where($this->db->quoteName('ip_hash') . ' = :hash')
            ->where($this->db->quoteName('hit_time') . ' >= :since')
            ->bind(':hash', $ipHash)
            ->bind(':since', $since);

        return (int) $this->db->setQuery($query)->loadResult();
    }
}
