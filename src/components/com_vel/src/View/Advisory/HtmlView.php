<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\View\Advisory;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Jed\Component\Vel\Administrator\Enum\Severity;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use Jed\Component\Vel\Site\Helper\VelHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

/**
 * One public advisory (brief §4.2).
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?object $item      = null;
    public ?Registry $params  = null;
    public AdvisoryState $state = AdvisoryState::PUBLISHED;
    public AdvisoryStatus $status = AdvisoryStatus::LIVE;
    public ?Severity $severity = null;

    /**
     * @var array<string, mixed>  The JSON v2 shape, which the template reads for the
     *                            computed bits (identity, dates in ISO).
     *
     * @since 4.0.0
     */
    public array $data = [];

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
        $model      = $this->getModel();
        $this->item = $model->getItem();

        if ($this->item === null) {
            throw new Exception(Text::_('COM_VEL_ERROR_ADVISORY_NOT_FOUND'), 404);
        }

        // The canonical URL carries the alias; an old or shortened link is redirected to it so
        // that search engines and checkers agree on one address.
        $app   = Factory::getApplication();
        $input = $app->getInput();

        if ($input->getMethod() === 'GET' && $input->get('format', 'html') === 'html') {
            $requested = (string) $input->get('id', '', 'string');

            if (!str_contains($requested, ':') || explode(':', $requested, 2)[1] !== (string) $this->item->alias) {
                $app->redirect(VelHelper::route($this->item), 301);
            }
        }

        $this->params   = $app->getParams('com_vel');
        $this->state    = AdvisoryState::tryFrom((string) $this->item->state) ?? AdvisoryState::PUBLISHED;
        $this->status   = AdvisoryStatus::tryFrom((string) $this->item->status) ?? AdvisoryStatus::LIVE;
        $this->severity = $this->item->severity ? Severity::tryFrom((string) $this->item->severity) : null;
        $this->data     = VelHelper::serializer()->serialize($this->item);

        $model->hit((int) $this->item->id);

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
        $title    = (string) $this->item->title . ' - ' . \sprintf('VEL-%04d', (int) $this->item->id);

        $document->setTitle($title);
        $document->setMetaData('description', mb_substr(AdvisorySerializer::text((string) $this->item->description), 0, 160));
        $document->addHeadLink($this->data['url'], 'canonical');
        $document->addHeadLink($this->data['links']['json'], 'alternate', 'rel', ['type' => 'application/json']);

        $document->setMetaData('og:title', $title, 'property');
        $document->setMetaData('og:type', 'article', 'property');
        $document->setMetaData('og:url', $this->data['url'], 'property');
        $document->setMetaData('og:description', mb_substr(AdvisorySerializer::text((string) $this->item->description), 0, 200), 'property');

        if ($this->state === AdvisoryState::RETIRED) {
            $document->setMetaData('robots', 'noindex, follow');
        }
    }
}
