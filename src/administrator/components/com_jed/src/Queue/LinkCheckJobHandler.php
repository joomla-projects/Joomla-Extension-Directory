<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Queue;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Link\LinkCheckService;

/**
 * Checks every link of one listing, on demand.
 *
 * The periodic pass rotates through the whole stock on its own schedule; this is for the moments
 * where waiting up to three days for it is the wrong answer - a listing that has just been edited,
 * or one a moderator is looking at right now. Same service, same validators, only the selection
 * differs.
 *
 * @since 4.1.0
 */
class LinkCheckJobHandler implements JobHandlerInterface
{
    /**
     * @param LinkCheckService $service The link check service.
     *
     * @since 4.1.0
     */
    public function __construct(private readonly LinkCheckService $service)
    {
    }

    /**
     * @param object $job The job row.
     *
     * @return array<string, mixed>
     *
     * @since 4.1.0
     */
    public function handle(object $job): array
    {
        $extensionId = (int) ($job->extension_id ?? 0);

        if ($extensionId <= 0) {
            return ['checked' => 0];
        }

        $results = $this->service->checkExtension($extensionId);

        return ['checked' => \count($results), 'results' => $results];
    }
}
