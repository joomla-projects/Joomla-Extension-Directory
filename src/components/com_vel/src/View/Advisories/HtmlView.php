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

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * The public list (brief §4.1).
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public array $items            = [];
    public ?Pagination $pagination = null;
    public ?Registry $state        = null;
    public ?Registry $params       = null;

    /**
     * @var array<string, int>
     *
     * @since 4.0.0
     */
    public array $counts = [];

    /**
     * @var array<string, mixed>  The active filter values, for the form.
     *
     * @since 4.0.0
     */
    public array $filters = [];

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

        $this->items      = (array) $model->getItems();
        $this->pagination = $model->getPagination();
        $this->state      = $model->getState();
        $this->counts     = $model->getStatusCounts();
        $this->params     = Factory::getApplication()->getParams('com_vel');

        $this->filters = [
            'status'   => (string) $this->state->get('filter.status', 'live'),
            'search'   => (string) $this->state->get('filter.search', ''),
            'severity' => (array) $this->state->get('filter.severity', []),
            'cvss_min' => $this->state->get('filter.cvss_min'),
            'cvss_max' => $this->state->get('filter.cvss_max'),
            'vendor'   => (string) $this->state->get('filter.vendor', ''),
            'type'     => (string) $this->state->get('filter.type', ''),
            'sort'     => (string) $this->state->get('list.ordering', 'a.date_published'),
            'dir'      => (string) $this->state->get('list.direction', 'DESC'),
        ];

        // The filters travel with the pagination links explicitly, so a page 2 of "critical and
        // live" is still that.
        foreach (['status', 'search', 'vendor', 'type', 'cvss_min', 'cvss_max'] as $key) {
            $value = $this->filters[$key] ?? null;

            if ($value !== null && $value !== '') {
                $this->pagination->setAdditionalUrlParam($key, (string) $value);
            }
        }

        if ($this->filters['severity'] !== []) {
            $this->pagination->setAdditionalUrlParam('severity', implode(',', $this->filters['severity']));
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function prepareDocument(): void
    {
        $document = $this->getDocument();
        $title    = (string) $this->params->get('page_title', Text::_('COM_VEL_LIST_HEADING'));
        $document->setTitle($title);

        // The feeds and the API, discoverable from the page head.
        $document->addHeadLink(Route::_('index.php?option=com_vel&view=advisories&format=feed&type=rss'), 'alternate', 'rel', ['type' => 'application/rss+xml', 'title' => Text::_('COM_VEL_FEED_TITLE')]);
        $document->addHeadLink(Route::_('index.php?option=com_vel&view=advisories&format=feed&type=atom'), 'alternate', 'rel', ['type' => 'application/atom+xml', 'title' => Text::_('COM_VEL_FEED_TITLE')]);
        $document->addHeadLink(Route::_('index.php?option=com_vel&view=api&resource=advisories'), 'alternate', 'rel', ['type' => 'application/json', 'title' => Text::_('COM_VEL_API_TITLE')]);

        $document->setMetaData('description', Text::_('COM_VEL_LIST_META_DESCRIPTION'));
    }
}
