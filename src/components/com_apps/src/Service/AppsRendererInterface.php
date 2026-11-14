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
 * Turns catalogue data into the HTML fragment a version of Joomla's Install from Web client expects.
 *
 * @since 4.0.0
 */
interface AppsRendererInterface
{
    /**
     * @param object[]    $categories As returned by AppsCatalogue::categories().
     * @param object[]    $items      As returned by AppsCatalogue::items().
     * @param object|null $category   The current category, if any.
     *
     * @since 4.0.0
     */
    public function listing(AppsRequest $request, array $categories, array $items, ?object $category): string;

    /**
     * @param string[] $versionLabels Joomla version id => label.
     *
     * @since 4.0.0
     */
    public function extension(AppsRequest $request, object $item, array $categories, ?object $category, array $versionLabels): string;

    /**
     * @since 4.0.0
     */
    public function message(AppsRequest $request, array $categories, string $key): string;
}
