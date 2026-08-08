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

/**
 * Decides whether a hit came from a person, and how confident that is.
 *
 * Two answers, not one, and the difference matters. `is_robot` means *this announced itself as a
 * crawler* - Googlebot says so in its user agent and there is no ambiguity. `suspicious` means
 * *this does not look like a person browsing* on weaker evidence: no user agent at all, an
 * address in a range the JED team has flagged, or a rate no human produces.
 *
 * **Marking, never blocking.** A false positive that blocks costs a real visitor their download
 * and the JED never finds out; one that mislabels costs a row in an aggregate. That asymmetry is
 * why this class has no opinion about serving the request.
 *
 * The user-agent list is the floor and not the ceiling. It catches the crawlers that identify
 * themselves, which in JED3's stock was 12.3% of all hits - a large share, and the easy one. What
 * it cannot catch is the traffic that pretends to be a browser, which is what the rate and range
 * signals are for. JED3 had a `suspicious` column and never set it in 2.1 million rows; this is
 * the part that was missing rather than merely absent.
 *
 * @since 4.1.0
 */
class BotDetector
{
    /**
     * Substrings that appear in the user agent of something that announces itself as a crawler.
     *
     * Lowercased, matched as substrings. Deliberately short and generic - `bot`, `crawler`,
     * `spider` cover the overwhelming majority, and the named entries are the ones that do not
     * contain any of those.
     *
     * @since 4.1.0
     */
    private const ROBOT_MARKERS = [
        'bot', 'crawler', 'spider', 'slurp', 'archiver', 'scraper',
        'facebookexternalhit', 'ia_archiver', 'mediapartners',
        'feedfetcher', 'wget', 'curl', 'python-requests', 'httpclient',
        'okhttp', 'go-http-client', 'java/', 'libwww', 'headlesschrome',
        'phantomjs', 'lighthouse', 'pingdom', 'uptimerobot', 'monitoring',
        'preview', 'validator', 'linkchecker',
    ];

    /**
     * Hits from one address within the window before the rate looks inhuman.
     *
     * Generous on purpose. A person comparing extensions in several tabs, or an office behind one
     * NAT address, produces a burst that must not be labelled - and mislabelling a shared address
     * would quietly write off a whole company's traffic. This is set where only automated access
     * reaches it.
     *
     * @since 4.1.0
     */
    public const RATE_LIMIT   = 60;
    public const RATE_MINUTES = 5;

    /**
     * Whether a user agent announces itself as a crawler.
     *
     * Pure, so the list can be tested directly against real strings.
     *
     * @param string|null $userAgent The `User-Agent` header.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isRobot(?string $userAgent): bool
    {
        $agent = strtolower(trim((string) $userAgent));

        if ($agent === '') {
            // No user agent at all is not a crawler announcing itself - it is something that did
            // not bother. That is `suspicious`, decided by the caller, not `is_robot`.
            return false;
        }

        foreach (self::ROBOT_MARKERS as $marker) {
            if (str_contains($agent, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether this hit looks automated on evidence weaker than a self-declaration.
     *
     * @param string|null $userAgent   The `User-Agent` header.
     * @param bool        $hasReferrer Whether the request arrived with a referrer.
     * @param bool        $knownRange  Whether the address is in a range `P1-05` flagged.
     * @param int         $recentHits  Hits from this address in the last {@see self::RATE_MINUTES}.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public static function isSuspicious(?string $userAgent, bool $hasReferrer, bool $knownRange, int $recentHits): bool
    {
        if (trim((string) $userAgent) === '') {
            return true;
        }

        if ($knownRange) {
            return true;
        }

        if ($recentHits >= self::RATE_LIMIT) {
            return true;
        }

        // A browser that arrives with no referrer is completely normal - a bookmark, a typed URL,
        // a link from a mail client, or any site with a strict referrer policy. On its own it
        // says nothing, which is why it only counts alongside a rate that is already high.
        return !$hasReferrer && $recentHits >= (int) (self::RATE_LIMIT / 2);
    }
}
