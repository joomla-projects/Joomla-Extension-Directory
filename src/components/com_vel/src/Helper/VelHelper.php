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

use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Small site-side conveniences.
 *
 * @since 4.0.0
 */
final class VelHelper
{
    /**
     * @param object $advisory An advisory row with id and alias.
     *
     * @return string  The SEF route.
     *
     * @since 4.0.0
     */
    public static function route(object $advisory): string
    {
        return Route::_('index.php?option=com_vel&view=advisory&id=' . (int) $advisory->id . ':' . (string) $advisory->alias);
    }

    /**
     * @return AdvisorySerializer  Configured for this site.
     *
     * @since 4.0.0
     */
    public static function serializer(): AdvisorySerializer
    {
        return new AdvisorySerializer(rtrim(Uri::root(), '/'), self::listPath());
    }

    /**
     * The path of the advisories menu item, e.g. "/vulnerable-extensions".
     *
     * @return string
     *
     * @since 4.0.0
     */
    public static function listPath(): string
    {
        static $path = null;

        if ($path === null) {
            $route = Route::_('index.php?option=com_vel&view=advisories', false);
            $base  = Uri::base(true);
            $path  = '/' . trim(str_starts_with($route, $base) ? substr($route, \strlen($base)) : $route, '/');
            $path  = preg_replace('~^/index\.php~', '', $path) ?? $path;
            $path  = preg_replace('/\?.*$/', '', $path) ?? $path;

            if ($path === '/' || $path === '') {
                $path = '/vulnerable-extensions';
            }
        }

        return $path;
    }

    /**
     * @param string $key      An option.
     * @param mixed  $fallback Default.
     *
     * @return mixed
     *
     * @since 4.0.0
     */
    public static function option(string $key, mixed $fallback = null): mixed
    {
        $value = ComponentHelper::getParams('com_vel')->get($key, null);

        return $value === null || $value === '' ? $fallback : $value;
    }
}
