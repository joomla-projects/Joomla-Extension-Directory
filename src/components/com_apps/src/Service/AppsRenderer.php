<?php

/**
 * @package JED
 *
 * @subpackage Apps
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Apps\Site\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the HTML fragment the Install from Web client puts on the page.
 *
 * A card or a row is opened by a click handler on its .load-extension element, so nothing inside
 * it may be an a.transcode link: the client would handle the click twice and leave its loading
 * overlay on the page.
 *
 * The markup follows what media/plg_installer_webinstaller/js/client.js looks for (ids, classes,
 * data attributes), and uses only the tags and attributes Joomla.sanitizeHtml lets through:
 * the client runs everything it receives through that filter and an element outside it is
 * removed together with its children. MarkupContractTest holds both lists.
 *
 * @since 4.0.0
 */
class AppsRenderer implements AppsRendererInterface
{
    /** Tags the client's sanitizer keeps; used to flatten a rendered description. */
    public const ALLOWED_DESCRIPTION_TAGS = '<a><b><br><code><div><em><h1><h2><h3><h4><h5><h6><hr><i>'
        . '<li><ol><p><pre><s><small><span><strong><sub><sup><u><ul>';

    protected const INCLUDE_LABELS = ['com' => 'COM_APPS_COMPONENT', 'mod' => 'COM_APPS_MODULE', 'plugin' => 'COM_APPS_PLUGIN',
        'plg'                               => 'COM_APPS_PLUGIN'];
    protected const INCLUDE_BADGES = ['com' => 'bg-success', 'mod' => 'bg-danger', 'plugin' => 'bg-secondary', 'plg' => 'bg-secondary'];

    /**
     * @param callable $translate  fn(string $key, mixed ...$args): string
     * @param callable $listingUrl fn(object $item): string, the public JED page of a listing
     * @param callable $markdown   fn(string $text): string, renders a stored description
     * @param string   $directoryUrl The JED home page, for the "listed on" link
     * @param string   $siteUrl      Absolute URL of the site root, to resolve stored logo paths
     */
    public function __construct(
        protected $translate,
        protected $listingUrl,
        protected $markdown,
        protected readonly string $directoryUrl,
        protected readonly string $siteUrl = ''
    ) {
    }

    /**
     * The home page, a category or a search result.
     *
     * @param object[]    $categories  As returned by AppsCatalogue::categories().
     * @param object[]    $items       As returned by AppsCatalogue::items().
     * @param object|null $category    The current category, if any.
     *
     * @since 4.0.0
     */
    public function listing(AppsRequest $request, array $categories, array $items, ?object $category): string
    {
        $html  = '<div class="com-apps-container"><div class="row">';
        $html .= '<div class="col-md-3">' . $this->sidebar($categories, $category) . '</div>';
        $html .= '<div class="col-md-9">' . $this->toolbar($request) . $this->breadcrumb($category, null);

        if ($request->searchTooShort) {
            $html .= $this->notice('COM_APPS_ERROR_SEARCH_SHORT');
        } elseif ($items === []) {
            $html .= $this->notice('COM_APPS_NO_RESULTS');
        } else {
            $html .= '<div class="items grid-container"><div class="row">';

            foreach ($items as $item) {
                $html .= $this->card($item);
            }

            $html .= '</div></div><div class="items list-container list-group hidden">';

            foreach ($items as $item) {
                $html .= $this->row($item);
            }

            $html .= '</div>';
        }

        return $html . '</div></div></div>';
    }

    /**
     * One listing. The toolbar is there too: the client wires up the search box after every response
     * and stops, loading overlay left on, when it is missing.
     *
     * @param string[] $versionLabels Joomla version id => label.
     *
     * @since 4.0.0
     */
    public function extension(AppsRequest $request, object $item, array $categories, ?object $category, array $versionLabels): string
    {
        $t    = $this->translate;
        $html = '<div class="com-apps-container"><div class="row">';
        $html .= '<div class="col-md-3">' . $this->sidebar($categories, $category) . '</div>';
        $html .= '<div class="col-md-9">' . $this->toolbar($request) . $this->breadcrumb($category, (string) $item->name)
            . '<div class="item-view">';
        $html .= '<div class="row"><div class="col-md-3">';

        $logo = $this->logo((string) $item->logo);

        if ($logo !== '') {
            $html .= '<img src="' . self::e($logo) . '" alt="" class="img-fluid">';
        }

        $html .= '</div><div class="col-md-9"><h2>' . self::e($item->name) . '</h2>';
        $html .= '<p class="rating">' . $this->rating($item) . '</p>';
        $html .= '<div class="item-details">';
        $html .= $this->detail('COM_APPS_VERSION', (string) $item->extension_version);
        $html .= $this->detail('COM_APPS_LICENSE', (string) $item->license);
        $html .= $this->detail('COM_APPS_DOWNLOAD_TYPE', $t('COM_APPS_TYPE_' . strtoupper((string) $item->type)));
        $html .= $this->detail('COM_APPS_ADDED_ON', $item->created ? substr((string) $item->created, 0, 10) : '');
        $html .= $this->detail('COM_APPS_COMPATIBLE', $this->versions((string) $item->joomla_versions, $versionLabels));
        $html .= '</div><p class="item-type">' . $this->badges((string) $item->extension_types, true) . '</p>';
        $html .= '<p class="item-buttons">' . $this->buttons($item) . '</p></div></div>';
        $html .= '<div class="item-desc">' . $this->description($item) . '</div>';

        return $html . '</div></div></div></div>';
    }

    /**
     * A page that only carries a message, with the standard sidebar and toolbar.
     *
     * @since 4.0.0
     */
    public function message(AppsRequest $request, array $categories, string $key): string
    {
        return '<div class="com-apps-container"><div class="row"><div class="col-md-3">' . $this->sidebar($categories, null)
            . '</div><div class="col-md-9">' . $this->toolbar($request) . $this->notice($key) . '</div></div></div>';
    }

    /**
     * The URL of one of this component's views, as the client expects to find it in a link.
     *
     * @since 4.0.0
     */
    public static function viewUrl(string $view, int $id = 0): string
    {
        return 'index.php?option=com_apps&format=json&view=' . $view . ($id > 0 ? '&id=' . $id : '');
    }

    protected function sidebar(array $categories, ?object $current): string
    {
        $t      = $this->translate;
        $openId = 0;

        if ($current !== null) {
            $openId = (int) ($current->level > 1 ? $current->parent_id : $current->id);
        }

        $html  = '<div class="com-apps-sidebar"><h3 class="h5">' . self::e($t('COM_APPS_CATEGORIES')) . '</h3>';
        $html .= '<ul class="nav flex-column"><li class="nav-item">' . $this->navLink(self::viewUrl('dashboard'), $t('COM_APPS_HOME'), $current === null) . '</li>';

        foreach ($categories as $category) {
            if ((int) $category->level !== 1) {
                continue;
            }

            $active = $current !== null && (int) $current->id === (int) $category->id;
            $html .= '<li class="nav-item">' . $this->navLink(self::viewUrl('category', (int) $category->id), (string) $category->title, $active);

            if ((int) $category->id === $openId) {
                $html .= $this->children($categories, $category, $current);
            }

            $html .= '</li>';
        }

        return $html . '</ul></div>';
    }

    protected function navLink(string $url, string $label, bool $active): string
    {
        return '<a class="nav-link transcode' . ($active ? ' active' : '') . '" href="' . self::e($url) . '">' . self::e($label) . '</a>';
    }

    protected function children(array $categories, object $parent, ?object $current): string
    {
        $html = '';

        foreach ($categories as $category) {
            if ((int) $category->parent_id !== (int) $parent->id) {
                continue;
            }

            $active = $current !== null && (int) $current->id === (int) $category->id;
            $html .= '<li class="nav-item">' . $this->navLink(self::viewUrl('category', (int) $category->id), (string) $category->title, $active) . '</li>';
        }

        return $html === '' ? '' : '<ul class="nav flex-column">' . $html . '</ul>';
    }

    protected function toolbar(AppsRequest $request): string
    {
        $t       = $this->translate;
        $options = '';

        foreach ([
            ''                   => 'COM_APPS_SORT_DEFAULT',
            'num_reviews'        => 'COM_APPS_SORT_REVIEWS',
            'score'              => 'COM_APPS_SORT_SCORE',
            'core_title'         => 'COM_APPS_SORT_NAME',
            'core_created_time'  => 'COM_APPS_SORT_NEWEST',
            'core_modified_time' => 'COM_APPS_SORT_UPDATED',
        ] as $value => $key) {
            $options .= '<option value="' . self::e($value) . '"' . ($request->ordering === $value ? ' selected="selected"' : '')
                . '>' . self::e($t($key)) . '</option>';
        }

        return '<div class="form-row mb-3"><div class="col-md"><div class="input-group">'
            . '<input type="text" class="form-control" name="filter_search" id="com-apps-searchbox" placeholder="'
            . self::e($t('COM_APPS_SEARCH')) . '">'
            . '<div class="input-group-append"><button type="button" class="btn btn-primary" id="search-extensions" aria-label="'
            . self::e($t('COM_APPS_SEARCH')) . '"><span class="icon-search" aria-hidden="true"></span></button>'
            . '<button type="button" class="btn btn-secondary" id="search-reset">' . self::e($t('COM_APPS_SEARCH_CLEAR')) . '</button>'
            . '</div></div></div><div class="col-md-auto"><div class="btn-group">'
            . '<button type="button" class="btn btn-secondary grid-view active" id="btn-grid-view" aria-label="'
            . self::e($t('COM_APPS_GRID_VIEW')) . '"><span class="icon-grid-view" aria-hidden="true"></span></button>'
            . '<button type="button" class="btn btn-secondary list-view" id="btn-list-view" aria-label="'
            . self::e($t('COM_APPS_LIST_VIEW')) . '"><span class="icon-list-view" aria-hidden="true"></span></button>'
            . '</div></div></div><div class="form-row mb-3"><div class="col-md">'
            . '<select class="form-select" id="com-apps-filter-joomla-version" name="filter_version" aria-label="'
            . self::e($t('COM_APPS_FILTER_VERSION')) . '"><option value="all"' . ($request->allVersions ? ' selected="selected"' : '')
            . '>' . self::e($t('COM_APPS_FILTER_ALL')) . '</option><option value="current"' . ($request->allVersions ? '' : ' selected="selected"')
            . '>' . self::e($t('COM_APPS_FILTER_CURRENT')) . '</option></select></div><div class="col-md">'
            . '<select class="form-select" id="com-apps-ordering" name="ordering" aria-label="' . self::e($t('COM_APPS_SORT')) . '">'
            . $options . '</select></div></div>';
    }

    protected function breadcrumb(?object $category, ?string $leaf): string
    {
        $t    = $this->translate;
        $html = '<ol class="breadcrumb"><li class="breadcrumb-item">' . $t('COM_APPS_BREADCRUMB_POPULAR', self::e($this->directoryUrl)) . '</li>';

        if ($category !== null) {
            $html .= '<li class="breadcrumb-item"><a class="transcode" href="' . self::e(self::viewUrl('category', (int) $category->id)) . '">'
                . self::e($category->title) . '</a></li>';
        }

        if ($leaf !== null) {
            $html .= '<li class="breadcrumb-item active">' . self::e($leaf) . '</li>';
        }

        return $html . '</ol>';
    }

    protected function notice(string $key): string
    {
        $t = $this->translate;

        return '<div class="alert alert-info">' . self::e($t($key)) . '</div>';
    }

    protected function card(object $item): string
    {
        $logo = $this->logo((string) $item->logo);

        return '<div class="col-sm-6 col-lg-4 col-xl-3"><div class="card"><div class="card-body load-extension" data-url="'
            . self::e(self::viewUrl('extension', (int) $item->id)) . '">'
            . ($logo !== '' ? '<img src="' . self::e($logo) . '" class="card-img-top" alt="">' : '')
            . '<h5 class="card-title">' . self::e($item->name) . '</h5>'
            . '<p class="small">' . $this->badges((string) $item->extension_types, false) . ' ' . $this->rating($item) . '</p>'
            . '<div class="card-text position-relative">' . self::e($this->excerpt((string) $item->intro)) . '<div class="fader"></div></div>'
            . '</div></div></div>';
    }

    protected function row(object $item): string
    {
        $logo = $this->logo((string) $item->logo);

        return '<div class="list-group-item"><div class="row load-extension" data-url="'
            . self::e(self::viewUrl('extension', (int) $item->id)) . '"><div class="col-md-2">'
            . ($logo !== '' ? '<img src="' . self::e($logo) . '" class="img-fluid" alt="">' : '')
            . '</div><div class="col-md-7"><h5>' . self::e($item->name) . '</h5><div class="position-relative">' . self::e($this->excerpt((string) $item->intro))
            . '<div class="fader"></div></div></div><div class="col-md-3">' . $this->badges((string) $item->extension_types, false)
            . '<p class="small">' . $this->rating($item) . '</p></div></div></div>';
    }

    protected function rating(object $item): string
    {
        $t     = $this->translate;
        $count = (int) $item->score_count;

        if ($count < 1) {
            return self::e($t('COM_APPS_NO_REVIEWS'));
        }

        $link = ($this->listingUrl)($item) . '#reviews';

        return '<a target="_blank" rel="noopener noreferrer" href="' . self::e($link) . '">'
            . self::e($t('COM_APPS_REVIEWS', $count, (int) round(((float) $item->score_overall) * 20))) . '</a>';
    }

    protected function detail(string $labelKey, string $value): string
    {
        $t = $this->translate;

        return $value === '' ? '' : '<div><strong>' . self::e($t($labelKey)) . '</strong> ' . self::e($value) . '</div>';
    }

    /**
     * @param string[] $labels Joomla version id => label.
     */
    protected function versions(string $stored, array $labels): string
    {
        $names = [];

        foreach ((array) json_decode($stored, true) as $id) {
            if (isset($labels[(string) $id])) {
                $names[] = $labels[(string) $id];
            }
        }

        return implode(', ', $names);
    }

    protected function badges(string $stored, bool $withText): string
    {
        $t    = $this->translate;
        $html = '';
        $seen = [];

        foreach ((array) json_decode($stored, true) as $token) {
            $token = strtolower(trim((string) $token));

            if (!isset(self::INCLUDE_LABELS[$token]) || isset($seen[self::INCLUDE_LABELS[$token]])) {
                continue;
            }

            $seen[self::INCLUDE_LABELS[$token]] = true;
            $label                              = $t(self::INCLUDE_LABELS[$token]);
            $html .= '<span aria-label="' . self::e($label) . '" class="badge ' . self::INCLUDE_BADGES[$token] . '">'
                . self::e($withText ? $label : strtoupper($label[0])) . '</span> ';
        }

        return $html;
    }

    protected function buttons(object $item): string
    {
        $t         = $this->translate;
        $name      = self::e($item->name);
        $updateUrl = self::url((string) $item->update_url);
        $download  = self::url((string) $item->download_url);
        $html      = '';

        if ((int) $item->uses_updater === 1 && $updateUrl !== '') {
            $html .= '<button type="button" class="btn btn-success" id="install-extension" data-downloadurl="'
                . self::e($updateUrl) . '" data-name="' . $name . '">' . self::e($t('COM_APPS_INSTALL')) . '</button> ';
        } elseif ($download !== '') {
            $html .= '<button type="button" class="btn btn-success" id="install-extension-from-external" data-downloadurl="'
                . self::e($download) . '" data-name="' . $name . '">' . self::e($t('COM_APPS_INSTALL_EXTERNAL')) . '</button> ';
        }

        $html .= '<a target="_blank" rel="noopener noreferrer" class="btn btn-primary" href="'
            . self::e(($this->listingUrl)($item)) . '">' . self::e($t('COM_APPS_LISTING')) . '</a> ';

        $developer = self::url((string) $item->developer_url);

        if ($developer !== '') {
            $html .= '<a target="_blank" rel="noopener noreferrer" class="btn btn-primary" href="' . self::e($developer)
                . '">' . self::e($t('COM_APPS_DEVELOPER_SITE')) . '</a>';
        }

        return $html;
    }

    protected function description(object $item): string
    {
        $html = ($this->markdown)((string) ($item->description ?? ''));

        return strip_tags($html, self::ALLOWED_DESCRIPTION_TAGS);
    }

    protected function excerpt(string $intro): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($intro)));

        return mb_strlen($text) > 300 ? rtrim(mb_substr($text, 0, 300)) . '...' : $text;
    }

    /**
     * A logo as an absolute URL: stored values are either that or a path under the site root.
     */
    protected function logo(string $stored): string
    {
        $stored = trim($stored);

        if ($stored !== '' && $stored[0] === '/' && ($stored[1] ?? '') !== '/' && $this->siteUrl !== '') {
            $stored = rtrim($this->siteUrl, '/') . $stored;
        }

        return self::url($stored);
    }

    /**
     * An http(s) URL, or the empty string: nothing else may reach href, src or the installer.
     */
    public static function url(string $value): string
    {
        $value = trim($value);

        return preg_match('#^https?://[^\s<>"\']+$#i', $value) === 1 ? $value : '';
    }

    protected static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
