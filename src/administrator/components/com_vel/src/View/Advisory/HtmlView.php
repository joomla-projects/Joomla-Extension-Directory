<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Advisory;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Service\JedBridge;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;

/**
 * The advisory editor.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $form = null;
    public mixed $item = null;

    /**
     * @var AdvisoryState
     *
     * @since 4.0.0
     */
    public AdvisoryState $state = AdvisoryState::DRAFT;

    /**
     * @var array<string, bool>
     *
     * @since 4.0.0
     */
    public array $can = [];

    /**
     * @var JedBridge
     *
     * @since 4.0.0
     */
    public JedBridge $jed;

    /**
     * @var string  The configured JED-ticket behaviour on publish.
     *
     * @since 4.0.0
     */
    public string $jedTicketMode = 'ask';

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

        // The item first, and the 404 before the form (com_abandonware's reasoning).
        $this->item = $model->getItem();

        if (!$this->item) {
            throw new Exception(Text::_('COM_VEL_ERROR_ADVISORY_NOT_FOUND'), 404);
        }

        $this->form = $model->getForm();

        $user        = Factory::getApplication()->getIdentity();
        $this->state = AdvisoryState::tryFrom((string) ($this->item->state ?? '')) ?? AdvisoryState::DRAFT;
        $this->jed   = new JedBridge(Factory::getContainer()->get(DatabaseInterface::class));

        $this->jedTicketMode = (string) \Joomla\CMS\Component\ComponentHelper::getParams('com_vel')->get('jed_open_ticket_on_publish', 'ask');

        $this->can = [
            'edit'        => (bool) $user->authorise('core.edit', 'com_vel'),
            'state'       => (bool) $user->authorise('core.edit.state', 'com_vel'),
            'publish'     => (bool) $user->authorise('vel.publish', 'com_vel'),
            'cve'         => (bool) $user->authorise('vel.cve', 'com_vel'),
            'communicate' => (bool) $user->authorise('vel.communicate', 'com_vel'),
            'jed'         => (bool) $user->authorise('vel.jed', 'com_vel') && $this->jed->available(),
        ];

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

        $isNew = empty($this->item->id);

        ToolbarHelper::title(
            $isNew
                ? Text::_('COM_VEL_ADVISORY_TITLE_NEW')
                : Text::sprintf('COM_VEL_ADVISORY_TITLE_EDIT', \sprintf('VEL-%04d', (int) $this->item->id)),
            'shield'
        );

        if ($this->can['edit']) {
            ToolbarHelper::apply('advisory.apply');
            ToolbarHelper::save('advisory.save');

            if (!$isNew) {
                ToolbarHelper::save2copy('advisory.save2copy');
            }
        }

        if (!$isNew && $this->can['cve']) {
            ToolbarHelper::link(
                'index.php?option=com_vel&task=cve.generate&id=' . (int) $this->item->id . '&' . \Joomla\CMS\Session\Session::getFormToken() . '=1',
                'COM_VEL_TOOLBAR_GENERATE_CVE',
                'download'
            );
        }

        ToolbarHelper::cancel('advisory.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
