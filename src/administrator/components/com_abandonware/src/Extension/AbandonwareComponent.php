<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Psr\Container\ContainerInterface;

/**
 * Component class for com_abandonware.
 *
 * @since 4.0.0
 */
class AbandonwareComponent extends MVCComponent implements
    BootableExtensionInterface,
    RouterServiceInterface
{
    use HTMLRegistryAwareTrait;
    use RouterServiceTrait;

    /**
     * @param ContainerInterface $container The container.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function boot(ContainerInterface $container): void
    {
    }
}
