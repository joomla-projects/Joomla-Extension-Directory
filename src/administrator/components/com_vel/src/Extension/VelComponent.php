<?php

/**
 * @package VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Psr\Container\ContainerInterface;

class VelComponent extends MVCComponent implements BootableExtensionInterface
{
    public function boot(ContainerInterface $container): void
    {
    }

    public function getDispatcher(CMSApplicationInterface $application): DispatcherInterface
    {
        return parent::getDispatcher($application);
    }
}
