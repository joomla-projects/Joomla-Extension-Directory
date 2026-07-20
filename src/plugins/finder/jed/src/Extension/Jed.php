<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.jed
 *
 * @copyright   (C) 2011 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Finder\Jed\Extension;

use Jed\Component\Jed\Site\Helper\RouteHelper;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Finder as FinderEvent;
use Joomla\CMS\Language\Text;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\QueryInterface;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Smart Search adapter for com_jed extensions.
 */
final class Jed extends Adapter implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * @var string
     */
    protected $context = 'Jed';

    /**
     * @var string
     */
    protected $extension = 'com_jed';

    /**
     * @var string
     */
    protected $layout = 'extension';

    /**
     * @var string
     */
    protected $type_title = 'Extension';

    /**
     * @var string
     */
    protected $table = '#__jed_extensions';

    /**
     * @var string
     */
    protected $state_field = 'state';

    /**
     * @var boolean
     */
    protected $autoloadLanguage = true;

    /**
     * Human-readable labels for the `type` field, keyed by stored value.
     *
     * @var array<string, string>
     * @since 4.0.0
     */
    private const TYPE_LABELS = [
        'free'     => 'COM_JED_GENERAL_TYPE_LABEL_FREE',
        'paid'     => 'COM_JED_GENERAL_TYPE_LABEL_PAID',
        'freemium' => 'COM_JED_GENERAL_TYPE_LABEL_FREEMIUM',
        'cloud'    => 'COM_JED_GENERAL_TYPE_LABEL_CLOUD',
    ];

    /**
     * Human-readable labels for the `extension_types` checkboxes, keyed by stored value.
     *
     * @var array<string, string>
     * @since 4.0.0
     */
    private const EXTENSION_TYPE_LABELS = [
        'com'      => 'COM_JED_EXTENSION_COMPONENT_LABEL',
        'mod'      => 'COM_JED_EXTENSION_MODULE_LABEL',
        'plugin'   => 'COM_JED_EXTENSION_PLUGIN_LABEL',
        'specific' => 'COM_JED_EXTENSION_SPECIFIC_LABEL',
    ];

    /**
     * `#__jed_joomla_versions.id` => `label`, populated once in {@see setup()}.
     *
     * @var array<string, string>
     * @since 4.0.0
     */
    private array $joomlaVersionLabels = [];

    /**
     * Returns an array of events this subscriber will listen to.
     *
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return array_merge(parent::getSubscribedEvents(), [
            'onFinderCategoryChangeState' => 'onFinderCategoryChangeState',
            'onFinderAfterDelete'         => 'onFinderAfterDelete',
            'onFinderAfterSave'           => 'onFinderAfterSave',
            'onFinderBeforeSave'          => 'onFinderBeforeSave',
            'onFinderChangeState'         => 'onFinderChangeState',
        ]);
    }

    /**
     * Method to update the item link information when the item category is
     * changed. This is fired when the item category is published or unpublished
     * from the list view.
     *
     * @param FinderEvent\AfterCategoryChangeStateEvent $event The event instance.
     *
     * @return void
     */
    public function onFinderCategoryChangeState(FinderEvent\AfterCategoryChangeStateEvent $event): void
    {
        if ($event->getExtension() === 'com_jed') {
            $this->categoryStateChange($event->getPks(), $event->getValue());
        }
    }

    /**
     * Method to remove the link information for items that have been deleted.
     *
     * @param FinderEvent\AfterDeleteEvent $event The event instance.
     *
     * @return void
     * @throws \Exception on database error.
     */
    public function onFinderAfterDelete(FinderEvent\AfterDeleteEvent $event): void
    {
        $context = $event->getContext();
        $table   = $event->getItem();

        if ($context === 'com_jed.extension') {
            $id = $table->id;
        } elseif ($context === 'com_finder.index') {
            $id = $table->link_id;
        } else {
            return;
        }

        $this->remove($id);
    }

    /**
     * Reindexes an extension that has been saved. Extensions have no item-level
     * `access` column, so unlike the core content/contacts adapters there is no
     * item access-change cascade here - only the category's access can change.
     *
     * @param FinderEvent\AfterSaveEvent $event The event instance.
     *
     * @return void
     * @throws \Exception on database error.
     */
    public function onFinderAfterSave(FinderEvent\AfterSaveEvent $event): void
    {
        $context = $event->getContext();
        $row     = $event->getItem();

        if ($context === 'com_jed.extension') {
            $this->reindex($row->id);
        }

        if ($context === 'com_categories.category') {
            $isNew = $event->getIsNew();

            if (!$isNew && $this->old_cataccess != $row->access) {
                $this->categoryAccessChange($row);
            }
        }
    }

    /**
     * @param FinderEvent\BeforeSaveEvent $event The event instance.
     *
     * @return void
     * @throws \Exception on database error.
     */
    public function onFinderBeforeSave(FinderEvent\BeforeSaveEvent $event): void
    {
        $context = $event->getContext();
        $row     = $event->getItem();
        $isNew   = $event->getIsNew();

        if ($context === 'com_categories.category' && !$isNew) {
            $this->checkCategoryAccess($row);
        }
    }

    /**
     * Method to update the link information for items that have been changed
     * from outside the edit screen (published, unpublished, archived, trashed).
     *
     * @param FinderEvent\AfterChangeStateEvent $event The event instance.
     *
     * @return void
     */
    public function onFinderChangeState(FinderEvent\AfterChangeStateEvent $event): void
    {
        $context = $event->getContext();
        $pks     = $event->getPks();
        $value   = $event->getValue();

        if ($context === 'com_jed.extension') {
            $this->itemStateChange($pks, $value);
        }

        if ($context === 'com_plugins.plugin' && $value === 0) {
            $this->pluginDisable($pks);
        }
    }

    /**
     * Method to index an item. The item must be a Result object.
     *
     * @param Result $item The item to index as a Result object.
     *
     * @return void
     *
     * @throws \Exception on database error.
     */
    protected function index(Result $item)
    {
        $item->setLanguage();

        if (ComponentHelper::isEnabled($this->extension) === false) {
            return;
        }

        $item->context = 'com_jed.extension';

        // #__jed_extensions has no per-item params column - only the global
        // component params apply.
        $item->params = clone ComponentHelper::getParams('com_jed', true);

        // Trigger the onContentPrepare event on the free-text fields.
        $item->summary = Helper::prepareContent($item->summary, $item->params, $item);
        $item->body    = Helper::prepareContent($item->body, $item->params, $item);

        // Create a URL as identifier to recognise items again.
        $item->url = $this->getUrl($item->id, $this->extension, $this->layout);

        // Build the necessary route and path information.
        $item->route = RouteHelper::getArticleRoute($item->id, $item->catid, $item->language);

        // Get the menu title if it exists.
        $title = $this->getItemMenuTitle($item->url);

        if (!empty($title) && $this->params->get('use_menu_title', true)) {
            $item->title = $title;
        }

        // Add the image - prefer the overview image, fall back to the logo.
        if (!empty($item->overview_image)) {
            $item->imageUrl = $item->overview_image;
            $item->imageAlt = $item->title ?? '';
        } elseif (!empty($item->logo)) {
            $item->imageUrl = $item->logo;
            $item->imageAlt = $item->title ?? '';
        }

        // Prefer the extension's JED developer identity over the raw Joomla username.
        $item->author = !empty($item->developer_name) ? $item->developer_name : $item->author;

        // Resolve the free/paid/freemium/cloud type to a human-readable label.
        $item->typeLabel = !empty(self::TYPE_LABELS[$item->type])
            ? Text::_(self::TYPE_LABELS[$item->type])
            : '';

        // Resolve the extension_types checkboxes (com/mod/plugin/specific) to human-readable labels.
        $extensionTypeValues = $this->decodeCheckboxValues($item->extension_types);
        $extensionTypeLabels = [];

        foreach ($extensionTypeValues as $value) {
            if (!empty(self::EXTENSION_TYPE_LABELS[$value])) {
                $extensionTypeLabels[] = Text::_(self::EXTENSION_TYPE_LABELS[$value]);
            }
        }

        $item->extensionTypesText = implode(', ', $extensionTypeLabels);

        // Resolve the joomla_versions checkboxes to human-readable labels (#__jed_joomla_versions).
        $joomlaVersionValues = $this->decodeCheckboxValues($item->joomla_versions);
        $joomlaVersionLabels = [];

        foreach ($joomlaVersionValues as $value) {
            if (!empty($this->joomlaVersionLabels[$value])) {
                $joomlaVersionLabels[] = $this->joomlaVersionLabels[$value];
            }
        }

        $item->joomlaVersionsText = implode(', ', $joomlaVersionLabels);

        // Add the free-text search instructions. developer_email is deliberately
        // excluded: it must never be exposed via public search result snippets.
        $item->addInstruction(Indexer::META_CONTEXT, 'author');
        $item->addInstruction(Indexer::META_CONTEXT, 'license');
        $item->addInstruction(Indexer::META_CONTEXT, 'typeLabel');
        $item->addInstruction(Indexer::META_CONTEXT, 'extensionTypesText');
        $item->addInstruction(Indexer::META_CONTEXT, 'joomlaVersionsText');

        // Translate the state. Extensions should only be indexed as published if the category is published.
        $item->state = $this->translateState($item->state, $item->cat_state);

        // Add the category taxonomy data.
        $categories = $this->getApplication()->bootComponent('com_jed')->getCategory(['published' => false, 'access' => false]);
        $category   = $categories->get($item->catid);

        if (!$category) {
            return;
        }

        // No item-level access column: the category's own access level is the only
        // real access gate available for this content, so it is used for the item too.
        $item->access = (int) $category->access;

        $item->addNestedTaxonomy('Category', $category, $this->translateState($category->published), $item->access, $item->language);

        // Add the type taxonomy data.
        if ($item->typeLabel !== '') {
            $item->addTaxonomy('Type', $item->typeLabel, $item->state, $item->access);
        }

        // Add the extension type taxonomy data (a single extension can be several types).
        foreach ($extensionTypeLabels as $label) {
            $item->addTaxonomy('Extension Type', $label, $item->state, $item->access);
        }

        // Add the Joomla version compatibility taxonomy data.
        foreach ($joomlaVersionLabels as $label) {
            $item->addTaxonomy('Joomla Version', $label, $item->state, $item->access);
        }

        // Add the license taxonomy data.
        if (!empty($item->license)) {
            $item->addTaxonomy('License', $item->license, $item->state, $item->access);
        }

        // Add the author taxonomy data.
        if (!empty($item->author)) {
            $item->addTaxonomy('Author', $item->author, $item->state, $item->access);
        }

        // Add the popular taxonomy data.
        if (!empty($item->popular)) {
            $item->addTaxonomy('Popular', Text::_('JYES'), $item->state, $item->access);
        }

        // Get content extras.
        Helper::getContentExtras($item);

        // Index the item.
        $this->indexer->index($item);
    }

    /**
     * Method to setup the indexer to be run. Preloads the Joomla version label
     * lookup table once per indexing run (rather than once per item).
     *
     * @return boolean True on success.
     */
    protected function setup()
    {
        $this->getApplication()->getLanguage()->load('com_jed', JPATH_SITE);

        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'label']))
            ->from($db->quoteName('#__jed_joomla_versions'));

        $this->joomlaVersionLabels = $db->setQuery($query)->loadAssocList('id', 'label') ?: [];

        return true;
    }

    /**
     * Decode a JSON-ish checkboxes field value (e.g. `["40","50"]`) into a plain array.
     *
     * @param mixed $value The raw column value.
     *
     * @return string[]
     */
    private function decodeCheckboxValues($value): array
    {
        if (empty($value)) {
            return [];
        }

        $decoded = json_decode((string) $value, true);

        return \is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), 'strlen')) : [];
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param mixed $query An object implementing QueryInterface or null.
     *
     * @return QueryInterface A database object.
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        $query = $query instanceof QueryInterface ? $query : $db->createQuery()
            ->select('a.id, a.name AS title, a.intro AS summary, a.description AS body')
            ->select('a.state, a.catid, a.created AS start_date, a.created_by, a.modified, a.modified_by')
            ->select('a.type, a.extension_types, a.joomla_versions, a.license')
            ->select('a.logo, a.overview_image, a.popular')
            ->select('c.title AS category, c.published AS cat_state, c.access AS cat_access')
            ->select('u.name AS author')
            ->select('d.developer_name')
            ->from('#__jed_extensions AS a')
            ->join('LEFT', '#__categories AS c ON c.id = a.catid')
            ->join('LEFT', '#__users AS u ON u.id = a.created_by')
            ->join('LEFT', '#__jed_developers AS d ON d.user_id = a.created_by');

        return $query;
    }

    /**
     * Method to get a query to retrieve item state, category state, and category
     * access for cascading state/access changes. Overridden because
     * `#__jed_extensions` has no `access` column - a constant is selected in its
     * place so the inherited categoryStateChange()/itemStateChange()/
     * categoryAccessChange() helpers keep working without referencing it.
     *
     * @return QueryInterface
     */
    protected function getStateQuery()
    {
        $query = $this->db->createQuery();

        $query->select('a.id')
            ->select('a.' . $this->state_field . ' AS state, c.published AS cat_state')
            ->select('1 AS access, c.access AS cat_access')
            ->from($this->table . ' AS a')
            ->join('LEFT', '#__categories AS c ON c.id = a.catid');

        return $query;
    }
}
