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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;
use UnexpectedValueException;

/**
 * Extension table
 *
 * @since 4.0.0
 */
class ExtensionTable extends Table implements TaggableTableInterface
{
    use TaggableTableTrait;

    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.extension';
        parent::__construct('#__jed_extensions', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Whether this save actually carried a `parent_confirmed` value.
     *
     * Distinguishes the JED team editing the listing in the backend - where the form always
     * submits the field, hence the yes/no radio rather than a checkbox - from
     * `ExtensionModel::approve()` promoting a developer's revision, where the column is absent
     * because `#__jed_extensions_history` does not have it. Only the second case may withdraw a
     * confirmation; see normaliseLinks().
     *
     * @var bool
     *
     * @since 4.0.0
     */
    private bool $parentConfirmedSubmitted = false;

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
        $user = $app->getIdentity();

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = $user->id;
        }

        // Preserve created_by on edit if not provided
        if ($src['id'] > 0 && empty($src['created_by'])) {
            // Load the existing record to get the original created_by
            $this->load($src['id']);
            if (!empty($this->created_by)) {
                $src['created_by'] = $this->created_by;
            }
        }

        if ($src['id'] == 0 && empty($src['modified_by'])) {
            $src['modified_by'] = $user->id;
        }

        if ($task == 'apply' || $task == 'save') {
            $src['modified_by'] = $user->id;
            $src['modified']    = $date->toSql();
        }

        if ($src['id'] == 0) {
            $src['created'] = $date->toSql();
        } elseif ($src['id'] > 0 && empty($src['created'])) {
            // Preserve created on edit if not provided
            if (!empty($this->created)) {
                $src['created'] = $this->created;
            }
        }

        // Noted before the checkbox defaulting below, which would otherwise make every save
        // look as though it had carried a value.
        $this->parentConfirmedSubmitted = \array_key_exists('parent_confirmed', (array) $src);

        $checkboxFields = ['checked_out', 'popular', 'requires_registration', 'approved', 'uses_updater'];

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
            $this->ordering = $this->getNextOrder();
        }

        $this->normaliseVideo();
        $this->normaliseLinks();

        return parent::check();
    }

    /**
     * Resolve and vet the two `P1-23` link columns, and keep the parent claim honest.
     *
     * The resolution and the three rejections live in {@see LinkedExtensions}, shared with
     * `ExtensionHistoryTable` so the frontend form and the backend form cannot diverge. What is
     * only true here is the last step: `parent_confirmed` belongs to the live row alone, and a
     * confirmation is a verdict on **one particular parent**. Moving the claim to a different
     * product has to withdraw it, or a developer could get a link confirmed against a small
     * extension nobody minds and then re-point it at VirtueMart, keeping the tick.
     *
     * Throws rather than returning false, for the reason normaliseVideo() gives: no caller in
     * this component reads check()'s return value.
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
        $selfId = (int) ($this->id ?? 0);

        $this->variant_of_id = LinkedExtensions::resolve($db, $this->variant_of_id ?? null, 'VARIANT');
        $this->parent_id     = LinkedExtensions::resolve($db, $this->parent_id ?? null, 'PARENT');

        LinkedExtensions::assertLinkable($db, $this->variant_of_id, $selfId, 'VARIANT');
        LinkedExtensions::assertLinkable($db, $this->parent_id, $selfId, 'PARENT');

        if ($this->variant_of_id !== null && !LinkedExtensions::mayLinkVariant($selfId, $this->variant_of_id)) {
            throw new UnexpectedValueException(Text::_('COM_JED_EXTENSION_LINK_VARIANT_NOT_YOURS'));
        }

        if ($selfId <= 0 || $this->parentConfirmedSubmitted) {
            return;
        }

        $storedParent = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('parent_id'))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $selfId, ParameterType::INTEGER)
        )->loadResult();

        if ((int) $storedParent !== (int) $this->parent_id) {
            $this->parent_confirmed = 0;
        }
    }

    /**
     * Keep `video_provider` and `video_id` in step with `video` (`P1-11`).
     *
     * On the table rather than in a model, because every save path leads through here - the
     * backend form, the frontend form, the moderation copy - and the legacy column only grew
     * into the mess `P0-03` measured because there was no single point that had to agree.
     *
     * A value the parser cannot recognise is rejected here rather than stored: the import had
     * to accept whatever history handed it, but nothing new should be allowed to arrive in a
     * shape the site cannot render. It throws rather than returning false, because every caller
     * in this component ignores check()'s return value - a boolean nobody reads would have let
     * the bad value straight through to store().
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
     * Check if a field is unique
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
            ->where($db->quoteName('id') . ' <> ' . (int) $this->{$this->_tbl_key});

        if (!empty($this->catid)) {
            $query->where($db->quoteName('catid') . ' = ' . (int) $this->catid);
        }

        $db->setQuery($query);
        $db->execute();

        return $db->getNumRows() == 0;
    }
}
