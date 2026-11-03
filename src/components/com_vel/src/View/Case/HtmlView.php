<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Case;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\ParticipantRole;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;

/**
 * The participant's case page.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?object $participant  = null;
    public ParticipantRole $role = ParticipantRole::OTHER;
    public array $thread         = [];
    public ?object $advisory     = null;
    public string $reference     = '';
    public string $token         = '';
    public ?Form $form           = null;

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
        /** @var \Jed\Component\Vel\Site\Model\CaseModel $model */
        $model             = $this->getModel();
        $this->participant = $model->getParticipant();

        if ($this->participant === null) {
            // A 404, not a 403: an unknown token must not confirm that a case exists.
            throw new Exception(Text::_('COM_VEL_ERROR_CASE_TOKEN'), 404);
        }

        $this->role      = $model->getRole();
        $this->thread    = $model->getThread();
        $this->advisory  = $model->getAdvisory();
        $this->reference = $model->getReference();
        $this->token     = $model->getToken();
        $this->form      = $model->getForm();

        $this->getDocument()->setTitle(Text::sprintf('COM_VEL_CASE_HEADING', $this->reference));
        $this->getDocument()->setMetaData('robots', 'noindex, nofollow');

        parent::display($tpl);
    }
}
