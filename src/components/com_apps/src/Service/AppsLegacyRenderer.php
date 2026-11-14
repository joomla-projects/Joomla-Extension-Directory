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
 * The markup for Joomla 3's Install from Web plugin.
 *
 * Its client is the old jQuery one. It puts the response into the page without sanitising it,
 * and the page calls back into its script through inline handlers: Joomla.loadweb() on a card,
 * Joomla.installfromweb() on the install button, Joomla.apps.initiateSearch() on the search
 * button. The layout is Bootstrap 2 (span3, thumbnails). This is what the current directory sends
 * to a release below 4 (or to one it cannot read); AppsLegacyRendererTest compares it with a
 * recorded response.
 *
 * Every value that reaches an inline handler is either an integer or a URL that passed
 * AppsRenderer::url(), and a name is escaped for a JavaScript string and then for the attribute.
 *
 * @since 4.0.0
 */
class AppsLegacyRenderer extends AppsRenderer
{
    protected const BADGES = ['com' => 'jcomponent', 'mod' => 'jmodule', 'plugin' => 'jplugin', 'plg' => 'jplugin'];

    /**
     * @since 4.0.0
     */
    public function listing(AppsRequest $request, array $categories, array $items, ?object $category): string
    {
        $html  = '<div class="com-apps-container"><div class="row-fluid"><div class="span3">'
            . $this->sidebar($categories, $category) . '</div><div class="span9">'
            . $this->toolbar($request) . '<div class="row-fluid">';

        if ($request->searchTooShort || $items === []) {
            $key = $request->searchTooShort ? 'COM_APPS_ERROR_SEARCH_SHORT' : 'COM_APPS_NO_RESULTS';

            return $html . '<blockquote><h4>' . self::e(($this->translate)($key)) . '</h4></blockquote></div></div></div></div>';
        }

        $html .= '<div class="item-view span12"><div class="items grid-container">' . $this->breadcrumb($category, null)
            . '<ul class="thumbnails">';

        foreach ($items as $item) {
            $html .= $this->card($item);
        }

        $html .= '</ul></div><div class="items list-container hidden">' . $this->breadcrumb($category, null) . '<ul class="thumbnails">';

        foreach ($items as $item) {
            $html .= $this->row($item);
        }

        return $html . '</ul></div></div></div></div></div></div>';
    }

    /**
     * @since 4.0.0
     */
    public function extension(AppsRequest $request, object $item, array $categories, ?object $category, array $versionLabels): string
    {
        $t    = $this->translate;
        $logo = $this->logo((string) $item->logo);

        $html  = '<div class="com-apps-container"><div class="row-fluid"><div class="span3">' . $this->sidebar($categories, $category)
            . '</div><div class="span9">' . $this->toolbar($request, false) . '<div class="item-view">'
            . '<div class="grid-header">' . $this->breadcrumb($category, (string) $item->name) . '</div>'
            . '<div class="full-item-container"><h2><span>' . self::e($item->name) . '</span></h2>'
            . '<div id="item-left-container" class="pull-left">'
            . ($logo !== '' ? '<img class="img img-polaroid" src="' . self::e($logo) . '" width="200" alt="">' : '')
            . '</div><div class="item-info-container pull-left"><div class="rating">' . $this->rating($item) . '</div><dl class="dl-horizontal">';

        foreach ([
            'COM_APPS_VERSION'       => (string) $item->extension_version,
            'COM_APPS_LICENSE'       => (string) $item->license,
            'COM_APPS_DOWNLOAD_TYPE' => $t('COM_APPS_TYPE_' . strtoupper((string) $item->type)),
            'COM_APPS_ADDED_ON'      => $item->created ? substr((string) $item->created, 0, 10) : '',
            'COM_APPS_COMPATIBLE'    => $this->versions((string) $item->joomla_versions, $versionLabels),
        ] as $key => $value) {
            if ($value !== '') {
                $html .= '<dt>' . self::e($t($key)) . '</dt><dd>' . self::e($value) . '</dd>';
            }
        }

        return $html . '</dl><div class="item-badge-container">' . $this->badges((string) $item->extension_types, true) . '</div></div>'
            . '<div class="clearfix"></div><div class="item-buttons form-actions">' . $this->buttons($item) . '</div>'
            . '<div class="item-desc">' . $this->description($item) . '</div><div class="clearfix"></div></div></div></div></div></div>';
    }

    /**
     * @since 4.0.0
     */
    public function message(AppsRequest $request, array $categories, string $key): string
    {
        return '<div class="com-apps-container"><div class="row-fluid"><div class="span3">' . $this->sidebar($categories, null)
            . '</div><div class="span9">' . $this->toolbar($request) . '<div class="row-fluid"><blockquote><h4>'
            . self::e(($this->translate)($key)) . '</h4></blockquote></div></div></div></div>';
    }

    protected function sidebar(array $categories, ?object $current): string
    {
        $t      = $this->translate;
        $openId = $current === null ? 0 : (int) ($current->level > 1 ? $current->parent_id : $current->id);

        $html = '<div class="com-apps-sidebar"><div class="scroll-pane well"><ul class="nav nav-list"><li class="nav-header"><h3>'
            . self::e($t('COM_APPS_CATEGORIES')) . '</h3></li>'
            . $this->entry(self::viewUrl('dashboard'), $t('COM_APPS_HOME'), $current === null);

        foreach ($categories as $category) {
            if ((int) $category->level !== 1) {
                continue;
            }

            $active = $current !== null && (int) $current->id === (int) $category->id;
            $html .= '<li' . ($active ? ' class="active"' : '') . '><a class="transcode' . ($active ? ' selected' : '') . '" href="'
                . self::e(self::viewUrl('category', (int) $category->id)) . '">' . self::e($category->title) . '</a>';

            if ((int) $category->id === $openId) {
                $html .= $this->children($categories, $category, $current);
            }

            $html .= '</li>';
        }

        return $html . '</ul></div></div>';
    }

    private function entry(string $url, string $label, bool $active): string
    {
        return '<li' . ($active ? ' class="active"' : '') . '><a class="transcode' . ($active ? ' selected' : '') . '" href="'
            . self::e($url) . '">' . self::e($label) . '</a></li>';
    }

    protected function children(array $categories, object $parent, ?object $current): string
    {
        $html = '';

        foreach ($categories as $category) {
            if ((int) $category->parent_id === (int) $parent->id) {
                $html .= $this->entry(
                    self::viewUrl('category', (int) $category->id),
                    (string) $category->title,
                    $current !== null && (int) $current->id === (int) $category->id
                );
            }
        }

        return $html === '' ? '' : '<ul class="nav nav-list">' . $html . '</ul>';
    }

    /**
     * @param bool $withControls False on a single extension, where the live directory shows the search only.
     */
    protected function toolbar(AppsRequest $request, bool $withControls = true): string
    {
        $t = $this->translate;

        $html = '<div class="row-fluid"><div class="span12"><div class="row-fluid btn-toolbar"><div class="span6">'
            . '<div class="filter-search btn-group"><div class="input-append"><input type="text" name="filter_search" id="com-apps-searchbox" placeholder="'
            . self::e($t('COM_APPS_SEARCH')) . '" value="' . self::e($request->search) . '" class="hasTooltip">'
            . '<button type="button" class="btn hasTooltip" onclick="Joomla.apps.initiateSearch();" aria-label="' . self::e($t('COM_APPS_SEARCH'))
            . '"><span class="icon-search" aria-hidden="true"></span></button>'
            . '<button type="button" class="btn hasTooltip" aria-label="' . self::e($t('COM_APPS_SEARCH_CLEAR'))
            . '" id="search-reset"><span class="icon-remove" aria-hidden="true"></span></button></div></div></div>';

        if (!$withControls) {
            return $html . '</div></div></div>';
        }

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

        return $html . '<div class="span6 text-right"><div class="btn-group">'
            . '<button type="button" class="btn grid-view active" id="btn-grid-view" aria-label="' . self::e($t('COM_APPS_GRID_VIEW'))
            . '"><span class="icon-grid-view" aria-hidden="true"></span></button>'
            . '<button type="button" class="btn list-view" id="btn-list-view" aria-label="' . self::e($t('COM_APPS_LIST_VIEW'))
            . '"><span class="icon-list-view" aria-hidden="true"></span></button></div></div></div>'
            . '<div class="row-fluid btn-toolbar"><div class="span12 text-right"><div class="btn-group select">'
            . '<select id="com-apps-ordering" name="ordering">' . $options . '</select></div></div></div></div></div>';
    }

    protected function breadcrumb(?object $category, ?string $leaf): string
    {
        $t = $this->translate;

        if ($leaf === null) {
            return '<ul class="breadcrumb"><li>' . $t('COM_APPS_BREADCRUMB_POPULAR', self::e($this->directoryUrl)) . '</li>'
                . ($category !== null ? '<li><a class="transcode" href="' . self::e(self::viewUrl('category', (int) $category->id)) . '">'
                    . self::e($category->title) . '</a></li>' : '') . '</ul>';
        }

        return '<ul class="breadcrumb">'
            . ($category !== null ? '<li><a class="transcode" href="' . self::e(self::viewUrl('category', (int) $category->id)) . '">'
                . self::e($category->title) . '</a></li><span class="divider"> / </span>' : '')
            . '<li>' . self::e($leaf) . '</li></ul>';
    }

    protected function card(object $item): string
    {
        $logo = $this->logo((string) $item->logo);
        $open = self::loadHandler((int) $item->id);

        return '<li class="item span3"><div class="thumbnail"><p class="rating center">' . $this->rating($item) . '</p>'
            . '<div onclick="' . $open . '"><div class="center item-image">'
            . ($logo !== '' ? '<img src="' . self::e($logo) . '" class="img center" />' : '') . '</div>'
            . '<ul class="item-type center">' . $this->badges((string) $item->extension_types, false, (string) $item->type) . '</ul>'
            . '<h4 class="center muted"><a class="transcode ajaxloaded" href="' . self::e(self::viewUrl('extension', (int) $item->id)) . '">'
            . self::e($item->name) . '</a></h4><div class="item-description">' . self::e($this->excerpt((string) $item->intro))
            . '<div class="fader">&nbsp;</div></div></div></div></li>';
    }

    protected function row(object $item): string
    {
        $open = self::loadHandler((int) $item->id);

        return '<li class="item span3"><div class="thumbnail"><div class="item-header" onclick="' . $open . '"><h4 class="muted">'
            . '<a class="transcode ajaxloaded" href="' . self::e(self::viewUrl('extension', (int) $item->id)) . '">' . self::e($item->name)
            . '</a></h4></div><div class="item-description" onclick="' . $open . '">' . self::e($this->excerpt((string) $item->intro))
            . '<div class="fader">&nbsp;</div></div><div class="item-icons"><div class="item-parts"><ul class="item-type center">'
            . $this->badges((string) $item->extension_types, false, (string) $item->type) . '</ul></div><div class="item-stars">'
            . '<p class="rating center">' . $this->rating($item) . '</p></div></div></div></li>';
    }

    protected function badges(string $stored, bool $withText, string $type = ''): string
    {
        $t    = $this->translate;
        $html = '';
        $seen = [];

        if ($type === 'paid') {
            $html .= '<span title="' . self::e($t('COM_APPS_TYPE_PAID')) . '" class="label label-jcommercial">$</span>';
        }

        foreach ((array) json_decode($stored, true) as $token) {
            $token = strtolower(trim((string) $token));

            if (!isset(self::BADGES[$token]) || isset($seen[self::BADGES[$token]])) {
                continue;
            }

            $seen[self::BADGES[$token]] = true;
            $label                      = $t(self::INCLUDE_LABELS[$token]);
            $html .= $withText
                ? '<span title="' . self::e($label) . '" class="badge jbadge badge-' . self::BADGES[$token] . '">' . self::e($label) . '</span>&nbsp;'
                : '<span title="' . self::e($label) . '" class="label label-' . self::BADGES[$token] . '">' . self::e(strtoupper($label[0])) . '</span>';
        }

        return $html;
    }

    protected function buttons(object $item): string
    {
        $t         = $this->translate;
        $updateUrl = self::url((string) $item->update_url);
        $download  = self::url((string) $item->download_url);
        $html      = '';

        if ((int) $item->uses_updater === 1 && $updateUrl !== '') {
            $html .= '<a class="install btn btn-success" href="#" onclick="' . self::installHandler($updateUrl, (string) $item->name)
                . '"><span class="icon-checkmark" aria-hidden="true"></span> ' . self::e($t('COM_APPS_INSTALL')) . '</a>&nbsp;&nbsp;&nbsp;';
        } elseif ($download !== '') {
            $html .= '<a target="_blank" rel="noopener noreferrer" class="btn btn-success" href="' . self::e($download)
                . '"><span class="icon-download" aria-hidden="true"></span> ' . self::e($t('COM_APPS_INSTALL_EXTERNAL')) . '</a>&nbsp;&nbsp;&nbsp;';
        }

        $html .= '<a target="_blank" rel="noopener noreferrer" class="btn btn-primary" href="' . self::e(($this->listingUrl)($item))
            . '"><span class="icon-list" aria-hidden="true"></span> ' . self::e($t('COM_APPS_LISTING')) . '</a>';

        $developer = self::url((string) $item->developer_url);

        if ($developer !== '') {
            $html .= '&nbsp;&nbsp;&nbsp;<a target="_blank" rel="noopener noreferrer" class="btn btn-primary" href="' . self::e($developer)
                . '"><span class="icon-share-alt" aria-hidden="true"></span> ' . self::e($t('COM_APPS_DEVELOPER_SITE')) . '</a>';
        }

        return $html;
    }

    /**
     * The handler of a card: loads the extension page.
     */
    private static function loadHandler(int $id): string
    {
        return self::e("Joomla.loadweb(apps_base_url+'" . self::viewUrl('extension', $id) . "');");
    }

    /**
     * The handler of the install button. The URL passed AppsRenderer::url() so it holds no quote,
     * and the name is made safe for a single-quoted JavaScript string first and for the attribute second.
     */
    private static function installHandler(string $url, string $name): string
    {
        $name = (string) preg_replace('/[\x00-\x1f\x{2028}\x{2029}]/u', '', $name);

        return self::e("return Joomla.installfromweb('" . $url . "', '" . addcslashes($name, "\\'") . "')");
    }
}
