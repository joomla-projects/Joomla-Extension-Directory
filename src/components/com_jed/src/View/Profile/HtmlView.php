<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Profile;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\Registry\Registry;

/**
 * View class for a developer/company profile.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected array $items;

    protected Pagination $pagination;

    protected Registry $state;

    protected Registry $params;

    protected mixed $developer;

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app   = Factory::getApplication();
        $model = $this->getModel();
        $model->setUseExceptions(true);

        // Loaded first: throws a 404 before anything else runs if the requested user isn't a
        // JED developer (doesn't exist, or owns no active extension).
        $this->developer  = $model->getDeveloper();
        $this->state      = $model->getState();
        $this->items      = $model->getItems();
        $this->pagination = $model->getPagination();
        $this->params     = $app->getParams('com_jed');

        $this->prepareDocument();
        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function prepareDocument(): void
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();
        $menu  = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', $this->developer->name);
        }

        $title = $this->params->get('page_title', $this->developer->name);

        if ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->getDocument()->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        // Add Breadcrumbs
        $pathway         = $app->getPathway();
        $breadcrumbTitle = $this->developer->name;

        if (!in_array($breadcrumbTitle, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbTitle);
        }
    }

    /**
     * Check if state is set
     *
     * @param mixed $state State
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function getState(mixed $state): bool
    {
        return $this->state->{$state} ?? false;
    }
}
