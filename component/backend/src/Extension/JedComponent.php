<?php
/**
 * @package    JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Jed\Component\Jed\Administrator\Service\Html\Jed;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Categories\CategoryServiceInterface;
use Joomla\CMS\Categories\CategoryServiceTrait;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Tag\TagServiceInterface;
use Joomla\CMS\Tag\TagServiceTrait;
use Psr\Container\ContainerInterface;
use function defined;

/**
 * Component class for com_jed
 *
 * @since  4.0.0
 */
class JedComponent extends MVCComponent implements BootableExtensionInterface, CategoryServiceInterface, RouterServiceInterface,
	TagServiceInterface
{
	use HTMLRegistryAwareTrait;
	use RouterServiceTrait;
	use CategoryServiceTrait, TagServiceTrait
	{
		CategoryServiceTrait::getTableNameForSection insteadof TagServiceTrait;
		CategoryServiceTrait::getStateColumnForSection insteadof TagServiceTrait;
	}

	/**
	 * Booting the extension. This is the function to set up the environment of the extension like
	 * registering new class loaders, etc.
	 *
	 * If required, some initial set up can be done from services of the container, e.g.
	 * registering HTML services.
	 *
	 * @param   ContainerInterface  $container  The container
	 *
	 * @return  void
	 *
	 * @since  4.0.0
	 */
	public function boot(ContainerInterface $container)
	{
		$this->getRegistry()->register('jed', new Jed);
	}

	/**
	 * Returns the table for the count items functions for the given section.
	 *
	 * @param   string|null  $section  The section
	 *
	 * @return  string|null
	 *
	 * @since  4.0.0
	 */
	protected function getTableNameForSection(string $section = null): string
	{
		return 'jed.extensions';
	}

	public function getDispatcher(CMSApplicationInterface $application): DispatcherInterface
	{
		// Load our custom Composer dependencies before dispatching the component
		require_once __DIR__ . '/../../vendor/autoload.php';

		return parent::getDispatcher($application);
	}


}
