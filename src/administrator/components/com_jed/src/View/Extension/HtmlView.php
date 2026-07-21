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
use Joomla\CMS\Router\Route;
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
    protected ?Form $form = null;
    protected mixed $forms;
    protected mixed $extensionimages;
    protected mixed $extensionimage;
    protected mixed $extensionform;
    protected ?stdClass $historyItem = null;
    protected array $images          = [];
    protected array $files           = [];
    protected array $categories      = [];
    protected array $maintainers     = [];
    protected array $history         = [];
    protected array $reviews         = [];

    protected ?object $compareLeft       = null;
    protected ?object $compareRight      = null;
    protected int $compareExtensionId    = 0;
    protected ?int $compareLeftId        = null;
    protected ?int $compareRightId       = null;
    protected ?int $compareApprovableId  = null;

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

        // DisplayController::display() never passes $tpl - it selects the template
        // via setLayout() instead, so branch on the actual layout, not $tpl.
        $layout = $this->getLayout();

        if ($layout === 'historylist') {
            $this->history = $model->getHistory();
            $this->item    = $model->getItem();
            // Rendered both as a quick-view modal fragment (tmpl=component, which
            // suppresses the toolbar/chrome regardless of this call) and as a full
            // standalone page (with the Compare toolbar button) when opened directly.
            $this->addToolbar();
            parent::display($tpl);
            return;
        }

        if ($layout === 'compare') {
            $input       = Factory::getApplication()->getInput();
            $extensionId = $input->getInt('id');
            $left        = $input->getInt('left', 0) ?: null;
            $right       = $input->getInt('right', 0) ?: null;

            // Only field/fieldset metadata is needed here (labels, grouping) - not
            // form data, so loadData is skipped.
            $this->form = $model->getForm([], false) ?: null;

            [$this->compareLeft, $this->compareRight, $this->compareRightId] =
                $model->getCompareItems($extensionId, $left, $right);
            $this->compareExtensionId = $extensionId;
            $this->compareLeftId      = $left;
            // "right" always resolves to a history row (explicit, or defaulted to
            // the latest one) - Approve always promotes whichever history row that
            // is, regardless of what "left" represents.
            $this->compareApprovableId = $this->compareRightId;

            $this->addToolbar();
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

        if ($this->getLayout() === 'compare') {
            ToolbarHelper::title(Text::_('COM_JED_EXTENSION_COMPARE_LABEL'), 'generic');
            ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_jed&view=extensions', false));

            if ($this->compareApprovableId && JedHelper::getActions()->get('core.edit')) {
                ToolbarHelper::custom('extension.approve', 'publish.png', 'publish_f2.png', 'COM_JED_APPROVE_LABEL', false);
            }

            return;
        }

        if ($this->getLayout() === 'historylist') {
            ToolbarHelper::title(Text::_('COM_JED_EXTENSION_HISTORY_LABEL'), 'generic');
            ToolbarHelper::back('JTOOLBAR_BACK', Route::_('index.php?option=com_jed&view=extensions', false));

            if (JedHelper::getActions()->get('core.edit')) {
                // Not a list-check button: the checkbox selection isn't tracked via
                // the standard cb/boxchecked convention - ExtensionController::
                // compareHistory() validates the selection itself.
                ToolbarHelper::custom('extension.compareHistory', 'contract.png', 'contract.png', 'COM_JED_EXTENSION_HISTORY_COMPARE_LABEL', false);
            }

            return;
        }

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
