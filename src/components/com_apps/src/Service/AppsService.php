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
 * Answers one request from the catalogue. No caching and no HTTP here.
 *
 * @since 4.0.0
 */
class AppsService
{
    /**
     * @param callable $translate fn(string $key): string
     */
    public function __construct(
        private readonly AppsCatalogue $catalogue,
        private readonly AppsRendererInterface $renderer,
        private readonly AppsRendererInterface $legacyRenderer,
        private $translate
    ) {
    }

    /**
     * @since 4.0.0
     */
    public function handle(AppsRequest $request): AppsResult
    {
        $t          = $this->translate;
        $renderer   = $request->legacy ? $this->legacyRenderer : $this->renderer;
        $categories = $this->catalogue->categories();
        $current    = null;

        if ($request->view === 'extension') {
            $item = $this->catalogue->item($request->id);

            if ($item === null) {
                return AppsResult::error(404, $t('COM_APPS_ERROR_NOT_FOUND'));
            }

            $current = self::category($categories, (int) $item->catid);

            return AppsResult::page(
                $renderer->extension($request, $item, $categories, $current, $this->catalogue->versionLabels()),
                AppsResult::KIND_BROWSE,
                $request->pluginStatus
            );
        }

        if ($request->view === 'category') {
            $current = self::category($categories, $request->id);

            if ($current === null) {
                return AppsResult::error(404, $t('COM_APPS_ERROR_NOT_FOUND'));
            }
        }

        $items = $request->searchTooShort ? [] : $this->catalogue->items($request, $categories);

        return AppsResult::page(
            $renderer->listing($request, $categories, $items, $current),
            $request->isSearch() ? AppsResult::KIND_SEARCH : AppsResult::KIND_BROWSE,
            $request->pluginStatus
        );
    }

    private static function category(array $categories, int $id): ?object
    {
        foreach ($categories as $category) {
            if ((int) $category->id === $id) {
                return $category;
            }
        }

        return null;
    }
}
