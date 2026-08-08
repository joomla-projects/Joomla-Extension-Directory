<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Link;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Url\UrlCheckResult;

/**
 * What a periodic check concluded about one link, in the four classes `4.9` asks for.
 *
 * The classes exist because a single "broken" bucket makes the whole feature useless. Roughly
 * every serious extension site runs bot protection, and the JED's checker arrives from a
 * datacentre address with an unfamiliar user agent - it gets 403s and 429s constantly from sites
 * that work perfectly for humans. Counting those the same as a domain that no longer resolves
 * would bury the real findings, and a team that has learned to ignore an alert has no alert.
 *
 * | Class      | What it means                                    | How it counts        |
 * | ---------- | ------------------------------------------------ | -------------------- |
 * | `OK`       | answered                                          | resets the counter   |
 * | `HARD`     | DNS gone, connection refused, 404/410             | full weight          |
 * | `SOFT`     | 403, 429, timeout, TLS trouble                    | never on its own     |
 * | `SEMANTIC` | reachable, but the document is not what it claims | full weight          |
 *
 * `SEMANTIC` is its own class rather than a kind of hard failure because it is the one a
 * developer can nearly always fix, and the one they most want to hear about: an update server
 * answering 200 with a document Joomla cannot read means their users are silently not being
 * offered updates.
 *
 * @since 4.0.0
 */
enum LinkStatus: string
{
    case OK       = 'ok';
    case HARD     = 'hard';
    case SOFT     = 'soft';
    case SEMANTIC = 'semantic';

    /**
     * Language keys of a validator outcome that means "the target is gone".
     *
     * Matched on the message key rather than on the HTTP status, because the validators already
     * did that reasoning once and repeating it here is how the two drift apart.
     *
     * @since 4.0.0
     */
    private const HARD_MESSAGES = [
        'COM_JED_URLCHECK_NOT_FOUND',
        'COM_JED_URLCHECK_REFUSED_DNS',
        'COM_JED_URLCHECK_FAILED_DNS',
        'COM_JED_URLCHECK_FAILED_UNREACHABLE',
        'COM_JED_URLCHECK_REFUSED_PRIVATE_ADDRESS',
        'COM_JED_URLCHECK_REFUSED_REDIRECT_LOOP',
        'COM_JED_URLCHECK_REFUSED_TOO_MANY_REDIRECTS',
        'COM_JED_URLCHECK_REFUSED_FORMAT',
        'COM_JED_URLCHECK_GIT_NOT_PUBLIC',
        'COM_JED_URLCHECK_GIT_NOT_A_REPOSITORY',
    ];

    /**
     * Validator notices that are **not** failures — the link answered, and the note is an
     * observation for whoever is reading the row, not a problem with the link.
     *
     * This list exists because the first pass over the real stock produced it. `P1-08`'s
     * validators are advisory in a *form*, where a developer may well want to hear "this download
     * link returns a page rather than a file" - but for link *health* that link is working, and
     * nearly every paid extension in the catalogue answers exactly that way. Counting them as
     * failures made `status <> 'ok'` true for most of the catalogue, which would have made the
     * moderation filter return everything and mean nothing.
     *
     * The message is still stored, so the observation is not lost. Only the counter ignores it.
     *
     * @since 4.0.0
     */
    private const HEALTHY_MESSAGES = [
        // A download link that leads to a product or cart page. Extremely common and correct.
        'COM_JED_URLCHECK_DOWNLOAD_IS_PAGE',
        // A page link that returns a file. Unusual, worth seeing, not broken.
        'COM_JED_URLCHECK_NOT_A_PAGE',
        // A human-readable changelog instead of changelog.xml - a legitimate choice.
        'COM_JED_URLCHECK_CHANGELOG_NOT_XML',
        // A collection file. Joomla accepts it as an update site; we simply cannot read a
        // version out of it.
        'COM_JED_URLCHECK_UPDATE_IS_COLLECTION',
    ];

    /**
     * Outcomes where the document was fetched but is not what the field promises.
     *
     * @since 4.0.0
     */
    private const SEMANTIC_MESSAGES = [
        'COM_JED_URLCHECK_UPDATE_EMPTY',
        'COM_JED_URLCHECK_UPDATE_NOT_A_FEED',
        'COM_JED_URLCHECK_UPDATE_IS_MANIFEST',
        'COM_JED_URLCHECK_UPDATE_NO_ENTRIES',
        'COM_JED_URLCHECK_UPDATE_BEHIND',
        'COM_JED_URLCHECK_UPDATE_NO_DOWNLOAD',
        'COM_JED_URLCHECK_CHANGELOG_MALFORMED',
        'COM_JED_URLCHECK_CHANGELOG_EMPTY',
        'COM_JED_URLCHECK_CHANGELOG_NO_VERSION',
    ];

    /**
     * Classify a validator's answer.
     *
     * Everything not named above is `SOFT`. That default is deliberate: an outcome nobody has
     * thought about must not be able to open a case by itself.
     *
     * @param UrlCheckResult $result The validator's answer.
     *
     * @return self
     *
     * @since 4.0.0
     */
    public static function fromResult(UrlCheckResult $result): self
    {
        if ($result->state === UrlCheckResult::STATE_OK) {
            return self::OK;
        }

        if (\in_array($result->message, self::HEALTHY_MESSAGES, true)) {
            return self::OK;
        }

        if (\in_array($result->message, self::HARD_MESSAGES, true)) {
            return self::HARD;
        }

        if (\in_array($result->message, self::SEMANTIC_MESSAGES, true)) {
            return self::SEMANTIC;
        }

        return self::SOFT;
    }

    /**
     * Whether this class counts towards escalation at all.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function counts(): bool
    {
        return $this === self::HARD || $this === self::SEMANTIC;
    }
}
