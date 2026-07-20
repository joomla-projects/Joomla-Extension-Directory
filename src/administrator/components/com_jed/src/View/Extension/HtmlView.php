<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\View\Extension;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\Model\ExtensionModel;
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;
use stdClass;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;

/**
 * View class for a single Extension.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;
    protected mixed $item;
    protected Form $form;
    protected mixed $forms;
    protected mixed $extensionimages;
    protected mixed $extensionscores;
    protected mixed $extensionimage;
    protected mixed $extensionscore;
    protected mixed $extensionform;
    protected ?stdClass $historyItem = null;
    protected array $images          = [];
    protected array $files           = [];
    protected array $categories      = [];
    protected array $maintainers     = [];
    protected array $history         = [];
    protected array $reviews         = [];



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
        /** @var ExtensionModel $model */
        $model = $this->getModel();
        $model->setUseExceptions(true);

        if ($tpl === 'historylist') {
            $this->history = $model->getHistory();
            $this->item    = $model->getItem();
            parent::display($tpl);
            return;
        }

        $this->item        = $model->getItem();
        $this->form        = $model->getForm();
        $this->state       = $model->getState();
        $this->images      = $model->getImages();
        $this->files       = $model->getFiles();
        $this->categories  = $model->getCategories();
        $this->maintainers = $model->getMaintainers();
        $this->history     = $model->getHistory();
        $this->reviews     = $model->getReviews();

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user  = $this->getCurrentUser();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->id);
        } else {
            $checkedOut = false;
        }

        $canDo = JedHelper::getActions();

        ToolbarHelper::title(Text::_('COM_JED_EXTENSION'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('extension.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('extension.save', 'JTOOLBAR_SAVE');
        }

        if (!$checkedOut && ($canDo->get('core.create'))) {
            ToolbarHelper::custom('extension.save2new', 'save-new.png', 'save-new_f2.png', 'JTOOLBAR_SAVE_AND_NEW', false);
        }

        // If an existing item, can save to a copy.
        if (!$isNew && $canDo->get('core.create')) {
            ToolbarHelper::custom('extension.save2copy', 'save-copy.png', 'save-copy_f2.png', 'JTOOLBAR_SAVE_AS_COPY', false);
        }

        // If an existing item, queue a manual, per-extension score recalculation job.
        if (!$isNew && !$checkedOut && $canDo->get('core.edit')) {
            ToolbarHelper::custom(
                'extension.recalculateScore',
                'refresh.png',
                'refresh.png',
                'COM_JED_TOOLBAR_RECALCULATE_SCORE',
                false
            );
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('extension.cancel', 'JTOOLBAR_CLOSE');
        }
    }
}
