<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Identityclaim;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Identity\IdentityLinks;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;

/**
 * The page where somebody who signed in through the Identity Portal can take over an old account.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected bool $isLoggedIn = false;

    protected bool $isPortalAccount = false;

    /**
     * Display the view
     *
     * @param string $tpl Template name
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $this->isLoggedIn = JedHelper::isLoggedIn();

        if ($this->isLoggedIn) {
            $userId = (int) Factory::getApplication()->getIdentity()->id;

            $this->isPortalAccount = (new IdentityLinks(Factory::getContainer()->get(DatabaseInterface::class)))
                ->guidForUser($userId) !== null;
        }

        $this->getDocument()->setTitle(Text::_('COM_JED_IDENTITY_TITLE'));

        parent::display($tpl);
    }
}
