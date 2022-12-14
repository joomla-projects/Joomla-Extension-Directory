<?php
/**
 * @package        JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Helper;

defined('_JEXEC') or die;


use DateTime;
use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\User;
use Joomla\Registry\Registry;
use function defined;

/**
 * JED Helper
 *
 * @package   JED
 * @since     4.0.0
 */
class JedHelper
{
	/**
	 * Add config toolbar to admin pages
	 *
	 * @since 4.0.0
	 */
	public static function addConfigToolbar(Toolbar $bar)
	{
		$bar->linkButton('tickets')
			->text(Text::_('COM_JED_TITLE_TICKETS'))
			->url('index.php?option=com_jed&view=jedtickets')
			->icon('fa fa-ticket-alt');
		$bar->linkButton('vulnerable')
			->text('Vulnerable Items')
			->url('index.php?option=com_jed&view=velvulnerableitems')
			->icon('fa fa-bug');

		$bar->customHtml('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');


		$configGroup = $bar->dropdownButton('config-group')
			->text(Text::_('COM_JED_GENERAL_CONFIG_LABEL'))
			->toggleSplit(false)
			->icon('fa fa-cog')
			->buttonClass('btn btn-action')
			->listCheck(false);

		$configChild = $configGroup->getChildToolbar();

		$configChild->linkButton('emailtemplates')
			->text('COM_JED_TITLE_MESSAGETEMPLATES')
			->icon('fa fa-envelope')
			->url('index.php?option=com_jed&view=messagetemplates');

		$configChild->linkButton('ticketcategories')
			->text('COM_JED_TITLE_TICKET_CATEGORIES')
			->icon('fa fa-folder')
			->url('index.php?option=com_jed&view=ticketcategories');

		$configChild->linkButton('ticketgroups')
			->text('COM_JED_TITLE_ALLOCATEDGROUPS')
			->icon('fa fa-user-friends')
			->url('index.php?option=com_jed&view=ticketallocatedgroups');

		$configChild->linkButton('ticketlinkeditemtypes')
			->text('COM_JED_TITLE_LINKED_ITEM_TYPES')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=ticketlinkeditemtypes');

		$configChild->linkButton('extensionsupplyoptions')
			->text('COM_JED_TITLE_EXTENSION_SUPPLY_OPTIONS')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=extensionsupplyoptions');

		$configChild->linkButton('copyjed3data')
			->text('COM_JED_TITLE_COPY_JED3_DATA')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=copyjed3data');

		$bar->customHtml('&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;');

		$debugGroup = $bar->dropdownButton('debug-group')
			->text('Debug')
			->toggleSplit(false)
			->icon('fa fa-cog')
			->buttonClass('btn btn-action')
			->listCheck(false);

		$debugChild = $debugGroup->getChildToolbar();

		$debugChild->linkButton('velabandonedreports')
			->text('VEL Abandoned Reports')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=velabandonedreports');

		$debugChild->linkButton('velreports')
			->text('VEL Reports')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=velreports');

		$debugChild->linkButton('veldeveloperupdates')
			->text('VEL Developer Updates')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=veldeveloperupdates');

		$debugChild->linkButton('velvulnerableitems')
			->text('VEL Vulnerable Items')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=velvulnerableitems');
		$debugChild->linkButton('ticketmessages')
			->text('Ticket Messages')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=ticketmessages');

		$debugChild->linkButton('ticketinternalnotes')
			->text('Ticket Internal Notes')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=ticketinternalnotes');

		$debugChild->linkButton('jedtickets')
			->text('JED Tickets')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=jedtickets');
		$debugChild->linkButton('extensions')
			->text('Extensions')
			->icon('fa fa-link')
			->url('index.php?option=com_jed&view=extensions');

	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @return registry
	 *
	 * @since    4.0.0
	 * @throws Exception
	 */
	public static function getActions(): registry
	{
		//$user   = Factory::getUser();

		$app = Factory::getApplication();

		$user   = $app->getSession()->get('user');
		$result = new Registry();

		$assetName = 'com_jed';

		$actions = array(
			'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete'
		);

		foreach ($actions as $action)
		{
			$result->set($action, $user->authorise($action, $assetName));
		}

		return $result;
	}

	/**
	 * Returns a span string containing an icon denoting approved status
	 *
	 * @return registry
	 *
	 * @since    4.0.0
	 * @throws Exception
	 */
	public static function getApprovedIcon(int $state): string
	{
		switch ($state)
		{
			// Rejected
			case '-1':
				$icon = 'unpublish';
				break;
			// Approved
			case '1':
				$icon = 'publish';
				break;
			// Awaiting response
			case '2':
				$icon = 'expired';
				break;
			// Pending
			case '0':
			default:
				$icon = 'pending';
				break;

		}

		return '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
	}

	/**
	 * Returns a span string containing an icon denoting published status
	 *
	 * @return registry
	 *
	 * @since    4.0.0
	 * @throws Exception
	 */
	public static function getPublishedIcon(int $state): string
	{
		switch ($state)
		{
			// Rejected
			case '-1':
				$icon = 'unpublish';
				break;
			// Approved
			case '1':
				$icon = 'publish';
				break;
			// Awaiting response
			case '2':
				$icon = 'expired';
				break;
			// Pending
			case '0':
			default:
				$icon = 'pending';
				break;

		}

		return '<span class="icon-' . $icon . '" aria-hidden="true"></span>';
	}

	/**
	 * Gets the current User .
	 *
	 * @return User\User
	 *
	 * @since    4.0.0
	 */
	public static function getUser(): User\User
	{
		try
		{
			$app = Factory::getApplication();

			return $app->getSession()->get('user');
		}
		catch (Exception $e)
		{
			return new User\User();
		}
	}

	/**
	 * Gets a user by ID number.
	 *
	 * @param $userId
	 *
	 * @return User\User
	 *
	 * @since    4.0.0
	 */
	public static function getUserById($userId): User\User
	{

		try
		{//$user   = Factory::getUser();
			$container   = Factory::getContainer();
			$userFactory = $container->get('user.factory');

			return $userFactory->loadUserById($userId);
		}
		catch (Exception $e)
		{
			return new User\User();
		}


	}

	/**
	 * Lock form fields
	 *
	 * This takes a form and marks all fields as readonly/disabled
	 *
	 * @param $form     form of fields
	 * @param $excluded array of fields not to lock
	 *
	 * @return bool
	 *
	 * @since 4.0.0
	 */
	public static function lockFormFields(Form $form, array $excluded): bool
	{

		$fields = $form->getFieldset();
		foreach ($fields as $field):
			if (in_array($field->getAttribute('name'), $excluded))
			{
				//Do Nothing
			}
			else
			{
				$form->setFieldAttribute($field->getAttribute('name'), 'disabled', 'true');
				$form->setFieldAttribute($field->getAttribute('name'), 'class', 'readonly');
				$form->setFieldAttribute($field->getAttribute('name'), 'readonly', 'true');
			}

		endforeach;

		return true;
	}

	/**
	 * Prettyfy a Data
	 *
	 * @param   string  $datestr  A String Date
	 *
	 * @since 4.0.0
	 **/
	public static function prettyDate(string $datestr): string
	{

		try
		{
			$d = new DateTime($datestr);

			return $d->format("d M y H:i");
		}
		catch (Exception $e)
		{
			return 'Sorry an error occured';
		}


	}


}

