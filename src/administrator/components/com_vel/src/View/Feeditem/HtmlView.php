<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Feeditem;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * One feed item beside the advisory it matches.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public mixed $item                        = null;
    public FeedClassification $classification = FeedClassification::UNKNOWN;
    public FeedDecision $decision             = FeedDecision::PENDING;
    public bool $canDecide                    = false;

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
        $this->item = $this->getModel()->getItem();

        if (!$this->item || empty($this->item->id)) {
            throw new Exception(Text::_('COM_VEL_ERROR_FEED_ITEM_NOT_FOUND'), 404);
        }

        $this->classification = FeedClassification::tryFrom((string) $this->item->classification) ?? FeedClassification::UNKNOWN;
        $this->decision       = FeedDecision::tryFrom((string) $this->item->decision) ?? FeedDecision::PENDING;
        $this->canDecide      = (bool) Factory::getApplication()->getIdentity()->authorise('vel.feeds', 'com_vel');

        Factory::getApplication()->getInput()->set('hidemainmenu', true);
        ToolbarHelper::title(Text::sprintf('COM_VEL_FEEDITEM_TITLE', (string) $this->item->external_id), 'shield');
        ToolbarHelper::cancel('feeditem.cancel', 'JTOOLBAR_CLOSE');

        parent::display($tpl);
    }
}
