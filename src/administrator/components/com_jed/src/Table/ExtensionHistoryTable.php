<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Listing\LinkedExtensions;
use Jed\Component\Jed\Administrator\Parser\VideoParser;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use UnexpectedValueException;

/**
 * ExtensionHistory table
 *
 * @since 4.0.0
 */
class ExtensionHistoryTable extends Table
{
    /**
     * Columns holding "checkboxes" field values, JSON-encoded on bind().
     *
     * @var array
     *
     * @since 4.0.0
     */
    protected $_jsonEncode = ['extension_types', 'joomla_versions'];

    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.extensionhistory';
        parent::__construct('#__jed_extensions_history', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     *
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param array|object $src    An associative array or object to bind to the Table instance.
     * @param array|string $ignore An optional array or space separated list of properties to ignore while binding.
     *
     * @return bool  True on success.
     *
     * @see    Table:bind
     * @throws Exception
     * @since  4.0.0
     */
    public function bind($src, $ignore = ''): bool
    {
        $date = Factory::getDate();
        $app  = Factory::getApplication();
        $task = $app->getInput()->get('task');

        // Support for alias field: alias
        if (empty($src['alias'])) {
            if (empty($src['name'])) {
                $src['alias'] = OutputFilter::stringURLSafe(date('Y-m-d H:i:s'));
            } else {
                if ($app->get('unicodeslugs') == 1) {
                    $src['alias'] = OutputFilter::stringURLUnicodeSlug(trim((string) $src['name']));
                } else {
                    $src['alias'] = OutputFilter::stringURLSafe(trim((string) $src['name']));
                }
            }
        }

        // Support for checkbox field: checked_out
        if (!isset($src['checked_out'])) {
            $src['checked_out'] = 0;
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = Factory::getApplication()->getIdentity()->id;
        }

        if ($src['id'] == 0) {
            $src['created'] = $date->toSql();
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified'] = $date->toSql();
        }

        $checkboxFields = ['popular', 'requires_registration', 'approved', 'uses_updater', 'active'];

        foreach ($checkboxFields as $field) {
            if (!isset($src[$field])) {
                $src[$field] = 0;
            }
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function check(): bool
    {
        // If there is an ordering column and this is a new row then get the next ordering value
        if (property_exists($this, 'ordering') && $this->id == 0) {
            $this->ordering = self::getNextOrder();
        }

        // Check if alias is unique
        if (!$this->isUnique('alias')) {
            $count        = 0;
            $currentAlias = $this->alias;
            while (!$this->isUnique('alias')) {
                $this->alias = $currentAlias . '-' . $count++;
            }
        }

        $this->normaliseVideo();
        $this->normaliseLinks();

        return parent::check();
    }

    /**
     * Resolve and vet the two `P1-23` link columns on a revision.
     *
     * The same rules as the live row, minus the confirmation half - a revision has no
     * `parent_confirmed` column, on purpose, so that approving a developer's edit can never
     * confirm their claim on somebody else's listing.
     *
     * `$this->id` is the revision's own primary key, not the listing's, so the self-link check
     * is given `extension_id` instead. Passing the revision id would compare a listing id
     * against a history id and let a listing name itself its own parent whenever the two
     * numbers happened to differ.
     *
     * @return void
     *
     * @throws UnexpectedValueException  When a link names no listing, or names this one.
     *
     * @since 4.0.0
     */
    protected function normaliseLinks(): void
    {
        $db     = $this->getDatabase();
        $selfId = (int) ($this->extension_id ?? 0);

        $this->variant_of_id = LinkedExtensions::resolve($db, $this->variant_of_id ?? null, 'VARIANT');
        $this->parent_id     = LinkedExtensions::resolve($db, $this->parent_id ?? null, 'PARENT');

        LinkedExtensions::assertLinkable($db, $this->variant_of_id, $selfId, 'VARIANT');
        LinkedExtensions::assertLinkable($db, $this->parent_id, $selfId, 'PARENT');

        if ($this->variant_of_id !== null && !LinkedExtensions::mayLinkVariant($selfId, $this->variant_of_id)) {
            throw new UnexpectedValueException(Text::_('COM_JED_EXTENSION_LINK_VARIANT_NOT_YOURS'));
        }
    }

    /**
     * Keep `video_provider` and `video_id` in step with `video` (`P1-11`).
     *
     * The revision carries the normalised pair as well, not just the raw string. Approval copies
     * a revision onto the live row column by column (`P1-02`), so a revision that only had the
     * raw value would leave the live row with a video and no provider.
     *
     * @return void
     *
     * @throws UnexpectedValueException  When the value is not a usable video.
     *
     * @since 4.0.0
     */
    protected function normaliseVideo(): void
    {
        $raw = trim((string) ($this->video ?? ''));

        $this->video = $raw;

        if ($raw === '') {
            $this->video_provider = null;
            $this->video_id       = null;

            return;
        }

        $video = VideoParser::parse($raw);

        if ($video === null) {
            throw new UnexpectedValueException(Text::_('COM_JED_EXTENSION_VIDEO_NOT_RECOGNISED'));
        }

        $this->video_provider = $video->provider;
        $this->video_id       = $video->id;
    }

    /**
     * Delete a record by id
     *
     * @param mixed $pk Primary key value to delete. Optional
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function delete($pk = null): bool
    {
        $this->load($pk);
        return parent::delete($pk);
    }

    /**
     * Get the type alias for the history table
     *
     * @return string  The alias as described above
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param bool $updateNulls True to update fields even if they are null.
     *
     * @return bool  True on success.
     *
     * @since 4.0.0
     */
    public function store($updateNulls = true): bool
    {
        return parent::store($updateNulls);
    }

    /**
     * Check if a field is unique across *other* extensions.
     *
     * The row being compared against is every revision that does not belong to this extension.
     * A listing's own revisions all carry the same alias by design - that is what a revision is.
     *
     * This read `$this->{$this->extension_id}`, a variable property: with extension_id 74 it
     * asked for `$this->{74}`, which does not exist, so the comparison became `extension_id <> 0`
     * and excluded nothing. Every earlier revision of the listing itself counted as a clash, so
     * check() renamed the alias on every single save - `chronoforms`, `chronoforms-0`,
     * `chronoforms-1` - and moderation copied the renamed alias onto the live row. A developer
     * who saved twice ended up with a different public URL and every inbound link to the old one
     * broken, with nothing anywhere saying so.
     *
     * @param string $field Name of the field
     *
     * @return bool True if unique
     * @since  4.0.0
     */
    private function isUnique(string $field): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName($field))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName($field) . ' = ' . $db->quote($this->$field))
            ->where($db->quoteName('extension_id') . ' <> ' . (int) $this->extension_id);

        if (!empty($this->catid)) {
            $query->where($db->quoteName('catid') . ' = ' . (int) $this->catid);
        }

        $db->setQuery($query);
        $db->execute();

        return $db->getNumRows() == 0;
    }
}
