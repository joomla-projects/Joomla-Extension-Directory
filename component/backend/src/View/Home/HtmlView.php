<?php

/**
 * @package    JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Home;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\HomeModel;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * View for JED Home.
 *
 * @package   JED
 * @since     4.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * The active filters
     *
     * @var    array
     * @since  4.0.0
     */
    public array $activeFilters = [];
    /**
     * The form filter
     *
     * @var    Form|null
     * @since  4.0.0
     */
    public ?Form $filterForm;
    /**
     * List of items to show
     *
     * @var    array
     * @since  4.0.0
     */
    protected array $items = [];

    /**
     * Pagination object
     *
     * @var    Pagination
     * @since  4.0.0
     */
    protected Pagination $pagination;

    /**
     * The model state
     *
     * @var    Registry
     * @since  4.0.0
     */
    protected Registry $state;

    /**
     * Add the page title and toolbar.
     *
     * @since  4.0.0
     * @throws Exception
     */
    private function addToolbar(): void
    {
        ToolBarHelper::title(Text::_('COM_JED'));

        $user = Factory::getApplication()->getIdentity();

        if (
            $user->authorise('core.admin', 'com_jed')
            || $user->authorise(
                'core.options',
                'com_jed'
            )
        ) {
            $bar = Toolbar::getInstance();

            JedHelper::addConfigToolbar($bar);


            ToolbarHelper::preferences('com_jed');
        }
    }

    /**
     * Execute and display a template script.
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     *
     * @return  void
     *
     * @since  4.0.0
     * @throws  Exception
     */
    public function display($tpl = null): void
    {
        /** @var HomeModel $model */

        $this->addToolbar();

        parent::display($tpl);
    }
}
