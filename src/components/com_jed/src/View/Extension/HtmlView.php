<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Site\View\Extension;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Hit\HitRecorder;
use Jed\Component\Jed\Administrator\Hit\HitType;
use Jed\Component\Jed\Administrator\Listing\ListingAccess;
use Jed\Component\Jed\Site\Helper\JedHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;

/**
 * View class for an individual Extension
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    protected Registry $state;

    protected mixed $item;

    protected mixed $form;
    /**
     * Get the Params
     *
     * @var   Registry
     * @since 4.0.0
     */
    protected Registry $params;

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
        $app          = Factory::getApplication();
        $user         = $this->getCurrentUser();
        $model        = $this->getModel();
        $model->setUseExceptions(true);
        $this->state  = $model->getState();
        $this->item   = $model->getItem();
        $this->params = $app->getParams('com_jed');
        //$this->form   = $model->getForm();

        if ($this->_layout == 'edit') {
            $authorised = $user->authorise('core.create', 'com_jed');

            if ($authorised !== true) {
                throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
            }
        }

        // A blocked listing is answered with the notice in place of the listing, not with the
        // listing plus a banner: none of the content - description, download links, reviews -
        // should be reachable while the block stands. Swapping the layout keeps that decision
        // in one line instead of threading a condition through the whole default template.
        if (($this->item->listing_access ?? null) === ListingAccess::BLOCKED) {
            $this->setLayout('blocked');
            $tpl = null;
        }

        $this->prepareDocument();
        parent::display($tpl);
    }

    /**
     * Prepares the document
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function prepareDocument(): void
    {
        $app   = Factory::getApplication();
        $menus = $app->getMenu();

        // Because the application sets a default page title,
        // We need to get it from the menu item itself
        $menu = $menus->getActive();

        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', Text::_('COM_JED_DEFAULT_PAGE_TITLE'));
        }

        $title = $this->params->get('page_title', '');

        if (empty($title)) {
            $title = $app->get('sitename');
        } elseif ($app->get('sitename_pagetitles', 0) == 1) {
            $title = Text::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ($app->get('sitename_pagetitles', 0) == 2) {
            $title = Text::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->getDocument()->setTitle($title);

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->getDocument()->setMetadata('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetadata('robots', $this->params->get('robots'));
        }

        // A blocked listing answers 200 so that visitors arriving from a search engine or an
        // old link are told why it is unusable (4.8) - but it must not stay in the index while
        // it says so, and this overrides whatever the menu item asked for.
        if (($this->item->listing_access ?? null) === ListingAccess::BLOCKED) {
            $this->getDocument()->setMetadata('robots', 'noindex, follow');
        } else {
            $this->addSocialMetadata();
        }

        // Counted here rather than in the model, because the model is also what the download
        // redirect and the moderation views load an item through - and a view is something a
        // visitor did on this page, not something anybody who reads the row did. Blocked and
        // deleted listings are excluded by sitting in the other branch (P1-01).
        (new HitRecorder(Factory::getContainer()->get(DatabaseInterface::class)))
            ->record((int) ($this->item->id ?? 0), HitType::VIEW);

        // Add Breadcrumbs
        $pathway        = $app->getPathway();
        $breadcrumbList = Text::_('COM_JED_EXTENSIONS');

        if (!in_array($breadcrumbList, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbList, "index.php?option=com_jed&view=extensions");
        }
        $breadcrumbTitle = Text::_('COM_JED_EXTENSION');

        if (!in_array($breadcrumbTitle, $pathway->getPathwayNames())) {
            $pathway->addItem($breadcrumbTitle);
        }
    }

    /**
     * OpenGraph and Twitter Card tags for the listing.
     *
     * The legacy site emits these and the JED gets heavy organic traffic, so a listing shared on
     * a social network or pasted into a chat has to arrive with its name, its summary and its
     * logo rather than as a bare URL. `<meta name="description">` is set from the same summary,
     * because the page previously had none of its own at all - it inherited whatever the menu
     * item said, which is the same text for every extension in the catalogue.
     *
     * Deliberately not emitted for a blocked listing: that page is `noindex` and says only that
     * the listing is unavailable. There is nothing there worth a rich preview.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    protected function addSocialMetadata(): void
    {
        $document = $this->getDocument();
        $name     = (string) ($this->item->name ?? '');

        if ($name === '') {
            return;
        }

        // The card summary, as plain text: the stored value is Markdown, and a card preview that
        // shows raw asterisks reads as broken.
        $summary = JedHelper::cardText($this->item->intro ?? null, $this->item->description ?? null);

        if ($summary === '') {
            $summary = $name;
        }

        $document->setDescription($summary);

        $tags = [
            'og:type'         => 'website',
            'og:site_name'    => (string) Factory::getApplication()->get('sitename'),
            'og:title'        => $name,
            'og:description'  => $summary,
            // The listing's own route, made absolute - not the requested URL. Otherwise a share
            // from a link carrying campaign parameters advertises that URL as the canonical one,
            // and the same listing ends up with as many og:urls as there are inbound links.
            // Route::_() already returns a root-relative path that includes the site's subfolder,
            // so only scheme/host/port are prefixed - Uri::root() would repeat the subfolder.
            'og:url'          => Uri::getInstance()->toString(['scheme', 'host', 'port'])
                . Route::_(
                    'index.php?option=com_jed&view=extension&catid=' . (int) $this->item->catid
                    . '&id=' . (int) $this->item->id,
                    false
                ),
            'twitter:card'    => 'summary',
            'twitter:title'   => $name,
            'twitter:description' => $summary,
        ];

        // The logo is the only image a listing has. Absolute, because a relative path in an
        // OpenGraph tag is not resolved by the consumers that matter.
        $logo = (string) ($this->item->logo_large ?? $this->item->logo ?? '');

        if ($logo !== '') {
            $tags['og:image']      = $logo;
            $tags['twitter:image'] = $logo;
            $tags['og:image:alt']  = $name;
            $tags['twitter:card']  = 'summary_large_image';
        }

        foreach ($tags as $property => $content) {
            if ($content === '') {
                continue;
            }

            // OpenGraph uses `property`, Twitter uses `name`. Joomla's setMetadata() writes
            // `name` unless told otherwise, and a consumer looking for og:title will not find
            // one written as a name attribute.
            $document->setMetaData($property, $content, str_starts_with($property, 'og:') ? 'property' : 'name');
        }
    }
}
