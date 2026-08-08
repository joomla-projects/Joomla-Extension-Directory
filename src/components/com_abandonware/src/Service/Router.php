<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

/**
 * Abandonware router.
 *
 * Three views and nothing clever. One thing this deliberately does **not** do is claim
 * `/vulnerable-extensions/`: P0-04 settled that the new VEL serves that path from this same
 * installation, and the one obligation the JED has there is to stay out of the way.
 *
 * @since 4.1.0
 */
class Router extends RouterView
{
    use MVCFactoryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * @param SiteApplication $app  The application.
     * @param AbstractMenu    $menu The menu.
     *
     * @since 4.1.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        parent::__construct($app, $menu);
        // From the container, not Factory::getDbo(), which is deprecated for removal in Joomla 7.
        $this->setDatabase(Factory::getContainer()->get(DatabaseInterface::class));

        $abandoned = new RouterViewConfiguration('abandoned');
        $this->registerView($abandoned);

        $case = new RouterViewConfiguration('case');
        $case->setKey('id')->setParent($abandoned);
        $this->registerView($case);

        $report = new RouterViewConfiguration('report');
        $this->registerView($report);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @param string $segment The segment.
     * @param array  $query   The query.
     *
     * @return string
     *
     * @since 4.1.0
     */
    public function getCaseId($segment, $query)
    {
        return $segment;
    }

    /**
     * @param int   $id    The case id.
     * @param array $query The query.
     *
     * @return array
     *
     * @since 4.1.0
     */
    public function getCaseSegment($id, $query)
    {
        return [$id];
    }
}
