<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\Service;

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
use Joomla\CMS\MVC\Factory\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryAwareTrait;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;

/**
 * Tickets Router.
 *
 * @package JED
 * @since   1.0.0
 */
class Router extends RouterView
{
    use MVCFactoryAwareTrait;
    use DatabaseAwareTrait;

    /**
     * Class constructor.
     *
     * @param SiteApplication   $app    Application-object that the router should use
     * @param AbstractMenu      $menu   Menu-object that the router should use
     * @param DatabaseInterface $db
     *
     * @since 1.0.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu)
    {
        parent::__construct($app, $menu);
        $this->setDatabase(Factory::getDbo());

        $tickets = new RouterViewConfiguration('tickets');
        $this->registerView($tickets);
        $ticket = new RouterViewConfiguration('ticket');
        $ticket->setKey('id')->setParent($tickets);
        $this->registerView($ticket);
        $ticketform = new RouterViewConfiguration('ticketform');
        $this->registerView($ticketform);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @param $segment
     * @param $query
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getTicketId($segment, $query)
    {
        return $segment;
    }

    /**
     * @param $id
     * @param $query
     *
     * @return array
     *
     * @since 1.0.0
     */
    public function getTicketSegment($id, $query)
    {
        return [$id];
    }
}
