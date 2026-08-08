<?php
/**
 * Joomla.org site template
 *
 * @copyright   Copyright (C) 2005 - 2023 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Http\HttpFactory;

/**
 * Helper class for the Joomla template
 */
class JoomlaTemplateHelper
{
	/**
	 * Retrieve the Google Tag Manager property ID for the current site
	 *
	 * Note that this helper method is only 'good' for live sites, for development environments no ID is returned
	 *
	 * @param   string  $siteUrl  The site URL without the scheme
	 *
	 * @return  string|boolean  The property ID or boolean false if one is not assigned
	 */
	public static function getGtmId($siteUrl)
	{
		switch ($siteUrl)
		{
			case 'api.joomla.org':
			{
				$id = 'GTM-NDWJB8';

				break;
			}

			case 'certification.joomla.org':
			{
				$id = 'GTM-PFP9MJ';

				break;
			}

			case 'community.joomla.org':
			{
				$id = 'GTM-WQNG7Z';

				break;
			}

			case 'conference.joomla.org':
			{
				$id = 'GTM-PZWNZR';

				break;
			}

			case 'developer.joomla.org':
			{
				$id = 'GTM-WJ36D4';

				break;
			}

			case 'docs.joomla.org':
			{
				$id = 'GTM-K6SPGS';

				break;
			}

			case 'downloads.joomla.org':
			{
				$id = 'GTM-KR9CX8';

				break;
			}

			case 'extensions.joomla.org':
			{
				$id = 'GTM-MH6RGF';

				break;
			}

			case 'forum.joomla.org':
			{
				$id = 'GTM-TWSN2R';

				break;
			}

			case 'framework.joomla.org':
			{
				$id = 'GTM-NX46ZP';

				break;
			}

			case 'issues.joomla.org':
			{
				$id = 'GTM-M7HXQ7';

				break;
			}

			case 'magazine.joomla.org':
			{
				$id = 'GTM-WG7372';

				break;
			}

			case 'opensourcematters.org':
			{
				$id = 'GTM-5GST4C';

				break;
			}

			case 'showcase.joomla.org':
			{
				$id = 'GTM-NKT9FP';

				break;
			}

			case 'tm.joomla.org':
			{
				$id = 'GTM-KZ7SM9';

				break;
			}

			case 'volunteers.joomla.org':
			{
				$id = 'GTM-P2Z55T';

				break;
			}

			case 'www.joomla.org':
			{
				$id = 'GTM-WWC8WL';

				break;
			}

			default:
				$id = false;

				break;
		}

		return $id;
	}

	/**
	 * Retrieve the "report an issue" link for the current site
	 *
	 * Note that this helper method is only 'good' for the live site, for development environments it will use a default link
	 *
	 * @param   string  $siteUrl  The site URL without the scheme
	 *
	 * @return  string  The issue link
	 */
	public static function getIssueLink($siteUrl)
	{
		$hasCustom = false;

		switch ($siteUrl)
		{
			case 'api.joomla.org':
			{
				$tag = 'japi';

				break;
			}

			case 'certification.joomla.org':
			{
				$tag = 'jcertif';

				break;
			}

			case 'community.joomla.org':
			{
				$tag = 'jcomm';

				break;
			}

			case 'conference.joomla.org':
			{
				$tag = 'jconf';

				break;
			}

			case 'developer.joomla.org':
			{
				$tag = 'jdev';

				break;
			}

			case 'docs.joomla.org':
			{
				$tag = 'jdocs';

				break;
			}

			case 'domains.joomla.org':
			{
				$tag = 'jdomain';

				break;
			}

			case 'downloads.joomla.org':
			{
				$tag = 'jdown';

				break;
			}

			case 'extensions.joomla.org':
			{
				$hasCustom = true;
				$tag       = 'jed';
				$url       = 'https://github.com/joomla/jed-issues/issues/new?body=Please%20describe%20the%20problem%20or%20your%20issue';

				break;
			}

			case 'forum.joomla.org':
			{
				$tag = 'jforum';

				break;
			}

			case 'joomlafoundation.org':
			{
				$tag = 'jfoundation';

				break;
			}

			case 'framework.joomla.org':
			{
				$hasCustom = true;
				$tag       = 'jfw';
				$url       = 'https://github.com/joomla/framework.joomla.org/issues/new?title=[FW%20Site]&body=Please%20state%20the%20nature%20of%20your%20development%20emergency';

				break;
			}

			case 'issues.joomla.org':
			{
				$hasCustom = true;
				$tag       = 'jissues';
				$url       = 'https://issues.joomla.org/tracker/jtracker';

				break;
			}

			case 'magazine.joomla.org':
			{
				$tag = 'jcm';

				break;
			}

			case 'opensourcematters.org':
			{
				$tag = 'josm';

				break;
			}

			case 'showcase.joomla.org':
			{
				$tag = 'jshow';

				break;
			}

			case 'tm.joomla.org':
			{
				$tag = 'jtm';

				break;
			}
			
			case 'volunteers.joomla.org':
			{
				$hasCustom = true;
				$tag       = 'jvols';
				$url       = 'https://github.com/joomla/volunteers.joomla.org/issues/new?body=Please%20describe%20the%20problem%20or%20your%20issue';

				break;
			}

			case 'www.joomla.org':
			{
				$tag = 'joomla.org';

				break;
			}

			default:
				$tag = '';

				break;
		}

		// Build the URL if we aren't using a custom source
		if (!$hasCustom)
		{
			$url = 'https://github.com/joomla/joomla-websites/issues/new?';

			// Do we have a tag?
			if (!empty($tag))
			{
				$url .= "title=[$tag]%20&";
			}

			$url .= 'body=Please%20describe%20the%20problem%20or%20your%20issue';
		}

		return $url;
	}

	/**
	 * Get the route for the login page
	 *
	 * @return  string
	 */
	public static function getLoginRoute()
	{
		// Check for SSO component
		$sso = ComponentHelper::getComponent('com_sso');

		if ($sso->id)
		{
			$itemid = self::getSsoRoute($sso->id);

			return 'index.php?Itemid=' . $itemid;
		}

		// URL routing since Joomla 4 means we don't need to retrieve the item id through the route helper, which was removed
		return 'index.php?option=com_users&view=login';
	}

	/**
	 * Check whether the current user is a guest.
	 *
	 * The identity is not necessarily set yet when the error document is rendered, so a missing identity is treated
	 * as a guest rather than dereferenced.
	 *
	 * @return  boolean
	 */
	private static function isGuest()
	{
		$identity = Factory::getApplication()->getIdentity();

		return $identity === null || $identity->guest;
	}

	/**
	 * Load the template's footer section
	 *
	 * @param   string   $lang    The language to request
	 * @param   boolean  $useCdn  True to load resource from the cdn, false from local instance
	 *
	 * @return  string
	 */
	public static function getTemplateFooter($lang, $useCdn = true)
	{
		$result = self::loadTemplateSection('footer', $lang, $useCdn);

		// Check for an error
		if ($result === 'Could not load template section.')
		{
			return $result;
		}

		// Replace the placeholders and return the result
		return strtr(
			$result,
			[
				'%reportroute%' => static::getIssueLink(Uri::getInstance()->toString(['host'])),
				'%loginroute%'  => Route::_(static::getLoginRoute()),
				'%logintext%'   => self::isGuest() ? Text::_('TPL_JOOMLA_FOOTER_LINK_LOG_IN') : Text::_('TPL_JOOMLA_FOOTER_LINK_LOG_OUT'),
				'%currentyear%' => date('Y'),
			]
		);
	}

	/**
	 * Load the template's CDN menu section
	 *
	 * @param   string   $lang    The language to request
	 * @param   boolean  $useCdn  True to load resource from the cdn, false from local instance
	 *
	 * @return  string
	 */
	public static function getTemplateMenu($lang, $useCdn = true)
	{
		return self::loadTemplateSection('menu', $lang, $useCdn);
	}

	/**
	 * Load the template section, caching the result if needed
	 *
	 * @param   string   $section  The section to be loaded
	 * @param   string   $lang     The language to request
	 * @param   boolean  $useCdn   True to load resource from the cdn, false from local instance
	 *
	 * @return  string
	 */
	private static function loadTemplateSection($section, $lang, $useCdn = true)
	{
		if (JDEBUG || !$useCdn)
		{
			$path = dirname(__DIR__) . "/cdn/layouts/$section/$lang.$section.html";

			if (!file_exists($path))
			{
				$path = dirname(__DIR__) . "/cdn/layouts/$section/en-GB.$section.html";
			}

			return file_get_contents($path);
		}

		/** @var \Joomla\CMS\Cache\Controller\CallbackController $cache */
		$cache = Factory::getContainer()
			->get(CacheControllerFactoryInterface::class)
			->createCacheController('callback', ['defaultgroup' => 'tpl_joomla']);

		// This is always cached regardless of the site's global setting
		$cache->setCaching(true);

		// Cache this for one day
		$cache->setLifeTime(1440);

		// Build the remote URL
		$url = "https://cdn.joomla.org/template/j4/renderer.php?section=$section&language=$lang";

		try
		{
			return $cache->get(
				function ($url)
				{
					// The framework factory does not set a user agent for us, so mirror what the CMS one used to send
					$options = ['userAgent' => (new Version)->getUserAgent('Joomla', true, false)];

					// Set a very short timeout to try and not bring the site down
					$response = (new HttpFactory)->getHttp($options)->get($url, [], 2);

					// Joomla\Http\Response is a plain PSR-7 response, it has no 'code'/'body' magic properties
					if ($response->getStatusCode() !== 200)
					{
						throw new RuntimeException('Could not load template section.');
					}

					return (string) $response->getBody();
				},
				[$url],
				md5(__METHOD__ . $section . $lang)
			);
		}
		catch (RuntimeException $e)
		{
			return 'Could not load template section.';
		}
	}

	/**
	 * Method to get the login/logout route configuration for the SSO.
	 *
	 * @param   integer  $componentId  The SSO component ID
	 *
	 * @return  mixed    Integer menu id on success, null on failure.
	 */
	public static function getSsoRoute($componentId)
	{
		// Get the items.
		$items = Factory::getApplication()->getMenu()->getItems('component_id', $componentId);
		$view  = self::isGuest() ? 'login' : 'logout';

		// Search for a suitable menu id.
		foreach ($items as $item)
		{
			if (isset($item->query['view']) && $item->query['view'] === $view)
			{
				return $item->id;
			}
		}

		return null;
	}
}
