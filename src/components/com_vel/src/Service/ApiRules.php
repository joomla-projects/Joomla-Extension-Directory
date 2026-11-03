<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;

/**
 * The two URL shapes StandardRules cannot express (VEL2-PRD §8.4):
 *
 *   /vulnerable-extensions/api/v2/<resource>[/<id>]   view=api&resource=<resource>&id=<id>
 *   /vulnerable-extensions/json-feed                  view=legacyfeed
 *
 * The first needs several segments under one view; the second needs a segment that is not a
 * view name, because the legacy feed's URL is fixed by the checkers that already read it.
 *
 * Attached after MenuRules (which takes the menu item's segments and sets view=advisories)
 * and before StandardRules (which must not see these segments).
 *
 * @since 4.0.0
 */
class ApiRules implements RulesInterface
{
    /**
     * @param RouterView $router The router.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly RouterView $router)
    {
    }

    /**
     * Make sure a URL for one of our views carries the advisories menu item, so the path
     * starts with /vulnerable-extensions even when built from another component's page.
     *
     * @param array $query The query.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function preprocess(&$query): void
    {
        if (isset($query['Itemid']) || !isset($query['view'])) {
            return;
        }

        if (!\in_array($query['view'], ['api', 'legacyfeed', 'advisory', 'advisories', 'report', 'fixnotice', 'case'], true)) {
            return;
        }

        $items = $this->router->menu->getItems('link', 'index.php?option=com_vel&view=advisories');

        if (!empty($items)) {
            $query['Itemid'] = (int) reset($items)->id;
        }
    }

    /**
     * @param array $segments The segments still to parse.
     * @param array $vars     The query vars found so far.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function parse(&$segments, &$vars): void
    {
        if (($vars['view'] ?? '') !== 'advisories' || $segments === []) {
            return;
        }

        $first = (string) $segments[0];

        if ($first === 'json-feed') {
            $vars['view']   = 'legacyfeed';
            $vars['format'] = 'json';
            array_shift($segments);

            return;
        }

        if ($first !== 'api') {
            return;
        }

        // api / v2 / resource[.json] / id[.json]
        $parts = $segments;
        array_shift($parts);

        $version = (string) ($parts[0] ?? '');

        if ($version !== 'v2') {
            // Unknown version: leave the segments, which ends in a 404.
            return;
        }

        $resource = (string) ($parts[1] ?? 'index');
        $id       = (string) ($parts[2] ?? '');

        $vars['view']     = 'api';
        $vars['resource'] = preg_replace('/\.json$/', '', $resource);
        $vars['format']   = 'json';

        if ($id !== '') {
            $vars['id'] = preg_replace('/\.json$/', '', $id);
        }

        $segments = [];
    }

    /**
     * @param array $query    The query.
     * @param array $segments The segments.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function build(&$query, &$segments): void
    {
        $view = (string) ($query['view'] ?? '');

        if ($view === 'legacyfeed') {
            $segments[] = 'json-feed';
            unset($query['view']);

            return;
        }

        if ($view !== 'api') {
            return;
        }

        $resource = (string) ($query['resource'] ?? 'index');
        $id       = (string) ($query['id'] ?? '');

        $segments[] = 'api';
        $segments[] = 'v2';
        $segments[] = $id !== '' ? $resource : $resource . '.json';

        if ($id !== '') {
            $segments[] = $id . '.json';
        }

        unset($query['view'], $query['resource'], $query['id'], $query['format']);
    }
}
