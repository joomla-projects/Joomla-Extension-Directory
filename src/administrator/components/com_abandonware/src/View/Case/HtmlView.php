<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\View\Case;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/**
 * One case, with everything needed to decide what to do about it.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $form   = null;
    public mixed $item   = null;

    /**
     * The status the case is in, resolved to the enum so the template can ask it questions rather
     * than compare strings.
     *
     * @var CaseStatus
     *
     * @since 4.0.0
     */
    public CaseStatus $status = CaseStatus::RECEIVED;

    /**
     * @var array<string, bool>
     *
     * @since 4.0.0
     */
    public array $can = [];

    /**
     * The configured grace period, offered as the default in the contact form.
     *
     * @var int
     *
     * @since 4.0.0
     */
    public int $graceDays = CaseService::DEFAULT_GRACE_DAYS;

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $model = $this->getModel();

        // The item first, and the 404 before the form. Built the other way round, a stale bookmark
        // to a deleted case raises a TypeError out of the form binding before this check is
        // reached - which is a 500 for something that is simply not there.
        //
        // No getErrors() check either: BaseModel::getErrors() is deprecated for removal in Joomla 7.
        $this->item = $model->getItem();

        if (!$this->item || empty($this->item->id)) {
            throw new Exception(Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'), 404);
        }

        $this->form = $model->getForm();

        $user         = Factory::getApplication()->getIdentity();
        $this->status = CaseStatus::tryFrom((string) ($this->item->status ?? '')) ?? CaseStatus::RECEIVED;

        $this->can = [
            'edit'    => (bool) $user->authorise('core.edit', 'com_abandonware'),
            'contact' => (bool) $user->authorise('abandonware.contact', 'com_abandonware'),
            'mark'    => (bool) $user->authorise('abandonware.mark', 'com_abandonware'),
            'resolve' => (bool) $user->authorise('abandonware.resolve', 'com_abandonware'),
        ];

        // From the container, not from the model: BaseDatabaseModel::getDatabase() is protected.
        $this->graceDays = (new CaseService(Factory::getContainer()->get(DatabaseInterface::class)))
            ->option('grace_days', CaseService::DEFAULT_GRACE_DAYS);

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        ToolbarHelper::title(
            Text::sprintf('COM_ABANDONWARE_CASE_TITLE', (string) ($this->item->extension_name ?? '')),
            'unpublish'
        );

        if ($this->can['edit']) {
            ToolbarHelper::apply('case.apply');
            ToolbarHelper::save('case.save');
        }

        ToolbarHelper::cancel('case.cancel', 'JTOOLBAR_CLOSE');
    }
}
