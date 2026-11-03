<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Advisories;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\Document\Feed\FeedItem;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\AbstractView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * RSS / Atom (brief §4.4). Takes the same filters as the list, so "?status=live&severity=critical"
 * is a feed of exactly that.
 *
 * @since 4.0.0
 */
class FeedView extends AbstractView
{
    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $app      = Factory::getApplication();
        $document = $this->getDocument();
        $model    = $this->getModel();

        // Fifty, newest first, whatever the page limit says.
        $model->setState('list.limit', 50);
        $model->setState('list.start', 0);

        $items  = (array) $model->getItems();
        $status = (string) $model->getState('filter.status', 'live');

        $document->setTitle(Text::_('COM_VEL_FEED_TITLE') . ($status !== 'all' && AdvisoryStatus::tryFrom($status) ? ' - ' . Text::_(AdvisoryStatus::from($status)->label()) : ''));
        $document->setDescription(Text::_('COM_VEL_FEED_DESCRIPTION'));
        $document->link = Route::_('index.php?option=com_vel&view=advisories');

        foreach ($items as $row) {
            $statusEnum   = AdvisoryStatus::tryFrom((string) $row->status) ?? AdvisoryStatus::LIVE;
            $severityEnum = $row->severity ? Severity::tryFrom((string) $row->severity) : null;

            $lines   = [];
            $lines[] = '<p><strong>' . Text::_($statusEnum->label()) . '</strong>'
                . ($severityEnum ? ' &middot; ' . Text::_($severityEnum->label()) . ($row->cvss_score !== null ? ' (' . number_format((float) $row->cvss_score, 1) . ')' : '') : '')
                . ($row->cve_id ? ' &middot; ' . htmlspecialchars((string) $row->cve_id, ENT_QUOTES, 'UTF-8') : '')
                . '</p>';
            $lines[] = '<p>' . Text::_('COM_VEL_HEADING_VENDOR') . ': ' . htmlspecialchars((string) $row->vendor, ENT_QUOTES, 'UTF-8')
                . '<br>' . Text::_('COM_VEL_HEADING_PRODUCTS') . ': ' . htmlspecialchars((string) $row->products, ENT_QUOTES, 'UTF-8')
                . ($row->fixed_versions ? '<br>' . Text::_('COM_VEL_HEADING_FIXED_IN') . ': ' . htmlspecialchars((string) $row->fixed_versions, ENT_QUOTES, 'UTF-8') : '')
                . '</p>';

            $item              = new FeedItem();
            $item->title       = '[' . Text::_($statusEnum->label()) . '] ' . (string) $row->title;
            $item->link        = Route::_('index.php?option=com_vel&view=advisory&id=' . (int) $row->id . ':' . (string) $row->alias);
            $item->description = implode('', $lines);
            $item->guid        = rtrim(Uri::root(), '/') . VelHelper::listPath() . '/' . (int) $row->id . '-' . (string) $row->alias;
            $item->date        = (string) ($row->date_published ?: $row->date_updated);
            $item->category    = [Text::_($statusEnum->label())];
            $item->author      = $app->get('sitename');

            if ($severityEnum) {
                $item->category[] = Text::_($severityEnum->label());
            }

            $document->addItem($item);
        }
    }
}
