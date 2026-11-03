<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Feed;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use DateTimeImmutable;
use Jed\Component\Vel\Administrator\Enum\FeedSource;

/**
 * One inbound feed.
 *
 * @since 4.0.0
 */
interface FeedSourceInterface
{
    /**
     * @return FeedSource
     *
     * @since 4.0.0
     */
    public function source(): FeedSource;

    /**
     * Records modified within the window. Generators are preferred so a large window streams.
     *
     * @param DateTimeImmutable $from    Window start (UTC).
     * @param DateTimeImmutable $to      Window end (UTC).
     * @param callable          $deadline Returns true when the time budget is spent.
     *
     * @return iterable<FeedRecord>
     *
     * @since 4.0.0
     */
    public function fetch(DateTimeImmutable $from, DateTimeImmutable $to, callable $deadline): iterable;

    /**
     * One record by id, for enrichment and for "fetch again".
     *
     * @param string $cveId The id.
     *
     * @return FeedRecord|null
     *
     * @since 4.0.0
     */
    public function fetchOne(string $cveId): ?FeedRecord;
}
