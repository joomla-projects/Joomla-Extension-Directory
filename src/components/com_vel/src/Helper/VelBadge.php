<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Helper;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Throwable;

/**
 * The warning a JED listing page shows while a published advisory names it (VEL2-PRD §6.9.1).
 *
 * Called from com_jed's extension template behind a class_exists() guard, so a JED without the
 * VEL installed renders nothing and raises nothing. One indexed query, cached per request.
 *
 * @since 4.0.0
 */
final class VelBadge
{
    /**
     * @var array<int, object[]>
     *
     * @since 4.0.0
     */
    private static array $cache = [];

    /**
     * @param int $extensionId The listing.
     *
     * @return object[]  Published advisories naming the listing, newest first.
     *
     * @since 4.0.0
     */
    public static function advisoriesFor(int $extensionId): array
    {
        if ($extensionId <= 0) {
            return [];
        }

        if (!isset(self::$cache[$extensionId])) {
            try {
                if (!ComponentHelper::isEnabled('com_vel') || (int) ComponentHelper::getParams('com_vel')->get('jed_badge_enabled', 1) !== 1) {
                    self::$cache[$extensionId] = [];
                } else {
                    self::$cache[$extensionId] = (new AdvisoryRepository(Factory::getContainer()->get(DatabaseInterface::class)))->publishedForExtension($extensionId);
                }
            } catch (Throwable $e) {
                self::$cache[$extensionId] = [];
            }
        }

        return self::$cache[$extensionId];
    }

    /**
     * @param int $extensionId The listing.
     *
     * @return string  HTML, or '' when there is nothing to say.
     *
     * @since 4.0.0
     */
    public static function render(int $extensionId): string
    {
        $advisories = self::advisoriesFor($extensionId);

        if ($advisories === []) {
            return '';
        }

        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_SITE . '/components/com_vel');

        $live = array_filter($advisories, static fn (object $a): bool => (string) $a->status === 'live');
        $kind = $live !== [] ? 'danger' : 'warning';
        $text = $live !== [] ? Text::_('COM_JED_EXTENSION_VEL_WARNING_LIVE') : Text::_('COM_JED_EXTENSION_VEL_WARNING_PATCHED');

        $html = '<div class="alert alert-' . $kind . ' jed-vel-badge" role="alert"><p class="mb-1"><strong>' . htmlspecialchars($text, ENT_QUOTES, 'UTF-8') . '</strong></p><ul class="mb-0">';

        foreach ($advisories as $advisory) {
            $html .= '<li><a href="' . htmlspecialchars(VelHelper::route($advisory), ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars(\sprintf('VEL-%04d', (int) $advisory->id) . ' ' . $advisory->title, ENT_QUOTES, 'UTF-8') . '</a>'
                . ((string) $advisory->fixed_version !== '' ? ' <span class="text-muted">(' . htmlspecialchars(Text::sprintf('COM_JED_EXTENSION_VEL_FIXED_IN', $advisory->fixed_version), ENT_QUOTES, 'UTF-8') . ')</span>' : '')
                . '</li>';
        }

        return $html . '</ul></div>';
    }
}
