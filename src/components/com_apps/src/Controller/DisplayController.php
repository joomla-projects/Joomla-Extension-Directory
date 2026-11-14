<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Apps\Site\Service\AppsCache;
use Jed\Component\Apps\Site\Service\AppsCatalogue;
use Jed\Component\Apps\Site\Service\AppsHeaders;
use Jed\Component\Apps\Site\Service\AppsLegacyRenderer;
use Jed\Component\Apps\Site\Service\AppsRenderer;
use Jed\Component\Apps\Site\Service\AppsRequest;
use Jed\Component\Apps\Site\Service\AppsResult;
use Jed\Component\Apps\Site\Service\AppsService;
use Jed\Component\Apps\Site\Service\InvalidRequestException;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

/**
 * The one entry point of com_apps: answers the Install from Web client with JSON.
 *
 * It does not go through a view or a template. See docs/install-from-web.md.
 *
 * @since 4.0.0
 */
class DisplayController extends BaseController
{
    /**
     * @param bool  $cachable  Not used.
     * @param array $urlparams Not used.
     *
     * @return static
     *
     * @since 4.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        $app       = $this->app;
        $container = \Joomla\CMS\Factory::getContainer();
        $params    = ComponentHelper::getParams('com_apps');
        $input     = $app->getInput();

        $app->getLanguage()->load('com_apps', JPATH_SITE . '/components/com_apps');

        $query = [];

        foreach (['view', 'id', 'filter_search', 'ordering', 'release', 'filter_version', 'callback', 'pv'] as $name) {
            $query[$name] = $input->get($name, '', 'raw');
        }

        try {
            $request = AppsRequest::fromQuery($query, (int) $params->get('search_min_length', 2));
        } catch (InvalidRequestException $e) {
            $this->send(AppsResult::error(400, Text::_($e->getMessage())), $params);
        }

        $cache = new AppsCache(
            $container->get(CacheControllerFactoryInterface::class),
            (int) $params->get('origin_ttl', 15),
            JPATH_CACHE . '/com_apps_locks'
        );

        $result = $cache->remember($request->cacheKey(), function () use ($container, $params, $request) {
            $translate = static fn (string $key, ...$args): string => $args === []
                ? Text::_($key)
                : sprintf(Text::_($key), ...$args);

            $listingUrl = static fn (object $item): string => Route::link(
                'site',
                'index.php?option=com_jed&view=extension&catid=' . (int) $item->catid . '&id=' . (int) $item->id,
                false,
                Route::TLS_IGNORE,
                true
            );

            $arguments = [
                $translate,
                $listingUrl,
                static fn (string $text): string => JedHelper::renderMarkdown($text),
                Uri::root(),
                Uri::root(),
            ];

            return (new AppsService(
                new AppsCatalogue($container->get(DatabaseInterface::class), (int) $params->get('max_items', 128)),
                new AppsRenderer(...$arguments),
                new AppsLegacyRenderer(...$arguments),
                $translate
            ))->handle($request);
        });

        $this->send($result, $params, $request->callback);

        return $this;
    }

    /**
     * Write the answer and end the request.
     *
     * Any cookie Joomla started for the visitor is dropped: a response that sets one cannot be
     * shared by a CDN, and nothing here depends on the session.
     *
     * @since 4.0.0
     */
    private function send(AppsResult $result, $params, string $callback = ''): never
    {
        $response = AppsHeaders::build(
            $result,
            (int) $params->get('edge_ttl', 3600),
            (int) $params->get('edge_ttl_search', 300),
            (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''),
            $callback
        );

        // Discard the visitor's session unwritten, so a request costs no row in #__session.
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_abort();
        }

        header_remove('Set-Cookie');
        header_remove('Expires');
        header_remove('Pragma');
        http_response_code($response['status']);

        foreach ($response['headers'] as $name => $value) {
            header($name . ': ' . $value, true);
        }

        echo $response['body'];

        $this->app->close();

        exit;
    }
}
