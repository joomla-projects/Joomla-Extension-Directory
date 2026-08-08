<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use InvalidArgumentException;
use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Jed\Component\Jed\Administrator\MediaHandling\ImageSize;
use Jed\Component\Jed\Administrator\Table\ExtensionHistoryTable;
use Jed\Component\Jed\Administrator\Table\ExtensionTable;
use Jed\Component\Jed\Administrator\Traits\ExtensionUtilities;
use Jed\Component\Jed\Administrator\Transfer\TransferService;
use Jed\Component\Jed\Site\Helper\JedscoreHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Form\FormFactoryInterface;
use Joomla\CMS\Helper\TagsHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Component\Users\Administrator\Table\NoteTable;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;
use RuntimeException;
use stdClass;

use function defined;

/**
 * Extension model.
 *
 * @since 4.0.0
 */
class ExtensionModel extends AdminModel
{
    use ExtensionUtilities;

    /**
     * @var string  Alias to manage history control
     *
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.extension';

    /**
     * @var string  The prefix to use with controller messages.
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';

    /**
     * @var stdClass  Item data
     *
     * @since 4.0.0
     */
    protected mixed $item;

    public function __construct(
        $config = [],
        ?MVCFactoryInterface $factory = null,
        ?FormFactoryInterface $formFactory = null
    ) {
        parent::__construct($config, $factory, $formFactory);
        $this->setUseExceptions(true);
    }

    protected function populateState()
    {
        parent::populateState();

        // Get the version ID of the record from the request.
        $version = Factory::getApplication()->getInput()->getInt('version');
        $this->setState($this->getName() . '.version', $version);
    }

    public function getItem($pk = null, $version = null)
    {
        $pk      = (!empty($pk)) ? $pk : (int) $this->getState($this->getName() . '.id');
        $version = (!empty($version)) ? $version : (int) $this->getState($this->getName() . '.version');
        $table   = $this->getTable('ExtensionHistory');

        if ($pk > 0) {
            // Attempt to load the row.
            if ($version) {
                $return = $table->load(['extension_id' => $pk, 'id' => $version]);
            } else {
                // Load the most recent history entry (highest id) for this extension.
                $latestId = $this->getLatestHistoryId($pk);

                $return = $latestId > 0 ? $table->load($latestId) : false;
            }

            // Check for a table object error.
            if ($return === false) {
                // If there was no underlying error, then the false means there simply was not a row in the db for this $pk.
                throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
            }
        }

        // Convert to \stdClass before adding other data
        $properties = get_object_vars($table);
        $item       = ArrayHelper::toObject($properties);

        if (property_exists($item, 'params')) {
            $registry     = new Registry($item->params);
            $item->params = $registry->toArray();
        }

        $db               = $this->getDatabase();
        $mapId            = $item->extension_id ?: (int) $item->id;
        $catQuery         = $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__jed_extensions_category_map'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $mapId, ParameterType::INTEGER);
        $item->categories = $db->setQuery($catQuery)->loadColumn() ?: [];

        // Pre-fill the "maintainer" subform with the extension's existing maintainers.
        $maintainerQuery = $db->getQuery(true)
            ->select($db->quoteName('user_id'))
            ->from($db->quoteName('#__jed_extensions_maintainers'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $mapId, ParameterType::INTEGER);
        $item->maintainer = array_map(
            static fn ($userId) => ['user_id' => (int) $userId],
            $db->setQuery($maintainerQuery)->loadColumn() ?: []
        );

        // Pre-fill the "tags" field with the live extension's current Joomla tags - tags live on
        // #__jed_extensions, not the history table, see ExtensionUtilities::storeTags().
        $item->tags = new TagsHelper();
        $item->tags->getTagIds($mapId, 'com_jed.extension');

        return $item;
    }

    /**
     * Get the id of the most recent (highest id) history entry for an extension.
     *
     * @param int $extensionId The extension id.
     *
     * @return int The history row id, or 0 if the extension has no history yet.
     *
     * @since 4.1.0
     */
    private function getLatestHistoryId(int $extensionId): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('id') . ')')
            ->from($db->quoteName('#__jed_extensions_history'))
            ->where($db->quoteName('extension_id') . ' = :eid')
            ->bind(':eid', $extensionId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult();
    }

    /**
     * Load the live `#__jed_extensions` row as a plain object, for use alongside
     * {@see getItem()} (which always loads from the history table) in the compare
     * layout.
     *
     * @param int $extensionId The extension id.
     *
     * @return object|null Null if no live row exists for this id.
     *
     * @since 4.1.0
     */
    public function getLiveItem(int $extensionId): ?object
    {
        /** @var ExtensionTable $table */
        $table = $this->getTable('Extension');

        if (!$table->load($extensionId)) {
            return null;
        }

        return ArrayHelper::toObject(get_object_vars($table));
    }

    /**
     * Resolve the two sides to show in the compare layout.
     *
     * @param int      $extensionId    The extension id.
     * @param int|null $leftHistoryId  A specific history id, or null for "the live row".
     * @param int|null $rightHistoryId A specific history id, or null for "the latest history row".
     *
     * @return array{0: object|null, 1: object|null, 2: int} [$left, $right, $resolvedRightHistoryId]
     *
     * @since 4.1.0
     */
    public function getCompareItems(int $extensionId, ?int $leftHistoryId, ?int $rightHistoryId): array
    {
        $left = $leftHistoryId
            ? $this->getItem($extensionId, $leftHistoryId)
            : $this->getLiveItem($extensionId);

        $rightHistoryId = $rightHistoryId ?: $this->getLatestHistoryId($extensionId);
        $right          = $rightHistoryId ? $this->getItem($extensionId, $rightHistoryId) : null;

        return [$left, $right, $rightHistoryId];
    }

    /**
     * Approve a pending history entry: overwrite the live `#__jed_extensions` row
     * with that entry's content, mark it the active history row, and point
     * `entry_version` at it.
     *
     * @param int $extensionId The extension id.
     * @param int $historyId   The `#__jed_extensions_history` id to promote to live.
     *
     * @return void
     *
     * @throws Exception If the history row doesn't belong to this extension.
     *
     * @since 4.1.0
     */
    public function approve(int $extensionId, int $historyId): void
    {
        /** @var ExtensionHistoryTable $historyTable */
        $historyTable = $this->getTable('ExtensionHistory');
        $historyTable->setUseExceptions(true);

        if (!$historyTable->load(['id' => $historyId, 'extension_id' => $extensionId])) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        $now    = Factory::getDate()->toSql();
        $userId = (int) $this->getCurrentUser()->id;

        // Record the verdict on the revision that was decided, so the decision is answerable per
        // revision and not only for the listing as a whole - an edit to a listing that is already
        // public is approved or rejected on its own.
        $historyTable->approved      = 1;
        $historyTable->approved_time = $now;
        $historyTable->approved_by   = $userId;
        $historyTable->store();

        // #__jed_extensions has no extension_id/active columns; #__jed_extensions_history
        // has no id-as-live-primary-key semantics - id is replaced with extension_id.
        $liveData = get_object_vars($historyTable);
        unset($liveData['extension_id'], $liveData['active']);
        $liveData['id'] = $extensionId;

        // The P1-01 carriers stay with the live row and are never promoted from a revision.
        // Without this, approving a pending edit would copy the `blocked` value the revision was
        // snapshotted with over the live one - so approving an edit made before a block would
        // silently lift that block, which is exactly what the separate carriers exist to prevent.
        // `state` goes the same way: online/offline is the developer's, not a moderation outcome.
        // `parent_confirmed` (P1-23) travels with them for the same reason: the developer's
        // claim about which product their add-on extends is theirs to make, but whether that
        // claim also appears on the other product's page is the team's to decide. It is not a
        // history column today, so this is belt and braces - add it to the revision table and
        // approving an edit would confirm the claim in the same motion.
        unset(
            $liveData['state'],
            $liveData['parent_confirmed'],
            $liveData['blocked'],
            $liveData['block_reason_code'],
            $liveData['block_reason_text'],
            $liveData['blocked_by'],
            $liveData['blocked_time'],
            $liveData['deleted'],
            $liveData['deleted_by'],
            $liveData['deleted_time'],
            $liveData['checked_out'],
            $liveData['checked_out_time']
        );

        /** @var ExtensionTable $liveTable */
        $liveTable = $this->getTable('Extension');
        $liveTable->setUseExceptions(true);

        if (!$liveTable->load($extensionId)) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        $liveTable->bind($liveData);
        $liveTable->check();
        $liveTable->store();

        // Marks this history row active (deactivating the rest) - existing, tested logic.
        $this->activateVersion($extensionId, $historyId);

        // Point the live row at the now-approved history entry.
        $this->updateEntryVersion($extensionId, $historyId);

        $this->notifyDeveloperOfDecision($extensionId, true, '', '');
    }

    /**
     * Reject a pending revision, with a reason from the shared vocabulary.
     *
     * The revision is kept rather than discarded: the JED team settled that a rejected
     * submission stays a record the developer can revise and resubmit, and throwing away what
     * they wrote would make that impossible. The verdict is recorded on the revision, and
     * mirrored onto the live row only when the listing has never been public - rejecting an edit
     * to an approved listing must not mark the listing itself rejected.
     *
     * @param int    $extensionId The extension id.
     * @param int    $historyId   The `#__jed_extensions_history` id being rejected.
     * @param string $reasonCode  A code from #__jed_block_reasons.
     * @param string $notes       Free text for the developer.
     *
     * @return void
     *
     * @throws Exception If the revision does not exist or the reason code is not a known one.
     *
     * @since 4.1.0
     */
    public function reject(int $extensionId, int $historyId, string $reasonCode, string $notes = ''): void
    {
        $reasonCode = trim($reasonCode);

        if ($reasonCode === '') {
            throw new Exception(Text::_('COM_JED_REJECT_ERROR_REASON_REQUIRED'));
        }

        if (!$this->blockReasonExists($reasonCode)) {
            throw new Exception(Text::sprintf('COM_JED_BLOCK_ERROR_REASON_UNKNOWN', $reasonCode));
        }

        /** @var ExtensionHistoryTable $historyTable */
        $historyTable = $this->getTable('ExtensionHistory');
        $historyTable->setUseExceptions(true);

        if (!$historyTable->load(['id' => $historyId, 'extension_id' => $extensionId])) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        $now    = Factory::getDate()->toSql();
        $userId = (int) $this->getCurrentUser()->id;
        $notes  = trim($notes);

        $historyTable->approved        = 0;
        $historyTable->approved_time   = $now;
        $historyTable->approved_by     = $userId;
        $historyTable->approved_reason = $reasonCode;
        $historyTable->approved_notes  = $notes;
        $historyTable->store();

        /** @var ExtensionTable $liveTable */
        $liveTable = $this->getTable('Extension');
        $liveTable->setUseExceptions(true);

        if (!$liveTable->load($extensionId)) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        // Only a listing that has never been public takes the verdict onto its live row. One that
        // is already approved stays approved and stays online; what was rejected is the edit.
        if ((int) $liveTable->approved !== 1) {
            $liveTable->approved        = 0;
            $liveTable->approved_time   = $now;
            $liveTable->approved_by     = $userId;
            $liveTable->approved_reason = $reasonCode;
            $liveTable->approved_notes  = $notes;
            $liveTable->store();
        }

        $this->notifyDeveloperOfDecision($extensionId, false, $reasonCode, $notes);
    }

    /**
     * The revision waiting for a decision, or 0 when there is none.
     *
     * "Newest row" is not the same thing, and using it was a latent bug: since `P1-01` every
     * block, unblock, soft delete and restore also writes a revision, so the newest row is
     * routinely a state change rather than something a developer submitted. Those are written
     * with `active = 0`, which is what separates them here.
     *
     * A revision is pending when the developer has submitted it (`active = 1`) and nobody has
     * decided it yet (`approved_time IS NULL`).
     *
     * @param int $extensionId The extension id.
     *
     * @return int  The history id, or 0.
     *
     * @since 4.1.0
     */
    public function getPendingHistoryId(int $extensionId): int
    {
        $db = $this->getDatabase();

        return (int) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__jed_extensions_history'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->where($db->quoteName('active') . ' = 1')
                ->where($db->quoteName('approved_time') . ' IS NULL')
                ->order($db->quoteName('id') . ' DESC')
                ->bind(':eid', $extensionId, ParameterType::INTEGER),
            0,
            1
        )->loadResult();
    }

    /**
     * Tell the developer what was decided about their listing.
     *
     * A rejection uses the mail template registered for its reason code, so the developer gets
     * the wording JED3 already used for that error rather than a generic "declined" - that
     * mapping is data in `#__jed_block_reasons`, not a match statement here. A code with no
     * template registered falls back to the generic one.
     *
     * @param int    $extensionId The extension id.
     * @param bool   $approved    Whether the listing was approved.
     * @param string $reasonCode  The rejection reason code, empty on approval.
     * @param string $notes       Free text for the developer.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function notifyDeveloperOfDecision(int $extensionId, bool $approved, string $reasonCode, string $notes): void
    {
        $db = $this->getDatabase();

        $listing = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['name', 'owner']))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $extensionId, ParameterType::INTEGER)
        )->loadAssoc();

        if ($listing === null || (int) $listing['owner'] <= 0) {
            return;
        }

        $template = 'com_jed.listing_approved';

        if (!$approved) {
            $template = (string) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('mail_template'))
                    ->from($db->quoteName('#__jed_block_reasons'))
                    ->where($db->quoteName('code') . ' = :code')
                    ->bind(':code', $reasonCode)
            )->loadResult() ?: 'com_jed.listing_rejected';
        }

        $owner = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $listing['owner']);

        if (empty($owner->email)) {
            return;
        }

        try {
            $mailer = new MailTemplate($template, Factory::getApplication()->getLanguage()->getTag());
            $mailer->addTemplateData([
                'EXTENSIONNAME' => (string) $listing['name'],
                'REASONCODE'    => $reasonCode,
                'REASONNOTES'   => $notes,
                'SITENAME'      => (string) Factory::getApplication()->get('sitename'),
            ]);
            $mailer->addRecipient($owner->email, $owner->name);
            $mailer->send();
        } catch (\Throwable $e) {
            // A listing must not stay undecided because a mail server was down. The decision is
            // already committed at this point; the failure is logged and the workflow continues.
            Log::add(
                sprintf('com_jed: could not notify the owner of extension %d: %s', $extensionId, $e->getMessage()),
                Log::WARNING,
                'com_jed'
            );
        }
    }

    /**
     * Check the live `#__jed_extensions` row out for editing.
     *
     * Overridden for the same reason as {@see publish()}: {@see getTable()} defaults to
     * `ExtensionHistoryTable`, so the inherited `AdminModel::checkout()` looked for the
     * *extension* id in the *history* table. History ids and extension ids are separate
     * sequences, so the load simply failed - and Joomla's failure path then called
     * `BaseModel::setError()`, which Joomla 6 removed, turning a wrong-table lookup into a
     * fatal 500 on every `task=extension.edit`.
     *
     * The lock belongs on the live row in any case: that is what the edit form identifies
     * itself by, and what a second editor has to be kept out of.
     *
     * @param int|null $pk The extension id.
     *
     * @return bool
     *
     * @throws Exception If the extension does not exist.
     *
     * @since 4.1.0
     */
    public function checkout($pk = null)
    {
        return $this->setLiveCheckout((int) ($pk ?: $this->getState($this->getName() . '.id')), true);
    }

    /**
     * Check the live `#__jed_extensions` row back in.
     *
     * See {@see checkout()} - `FormController::cancel()` and the save tasks reach this with an
     * extension id, and it has to land on the same table the checkout did.
     *
     * @param int|null $pk The extension id.
     *
     * @return bool
     *
     * @throws Exception If the extension does not exist.
     *
     * @since 4.1.0
     */
    public function checkin($pk = null)
    {
        return $this->setLiveCheckout((int) ($pk ?: $this->getState($this->getName() . '.id')), false);
    }

    /**
     * Take or release the editing lock on a live extension row.
     *
     * @param int  $extensionId The extension id.
     * @param bool $checkOut    True to take the lock for the current user, false to release it.
     *
     * @return bool  True when the lock was taken or released; false when the listing does not
     *               exist or the lock is held by someone else.
     *
     * @since 4.1.0
     */
    private function setLiveCheckout(int $extensionId, bool $checkOut): bool
    {
        if ($extensionId <= 0) {
            return true;
        }

        /** @var ExtensionTable $table */
        $table = $this->getTable('Extension');

        // Deliberately not setUseExceptions(true) and deliberately not throwing: the callers in
        // FormController treat false as "cannot edit this" and redirect with a message. A
        // missing id is a stale link, not a server fault, and must not become a 500 - which is
        // exactly what the inherited implementation did once Joomla 6 removed setError().
        if (!$table->load($extensionId)) {
            return false;
        }

        $userId = (int) $this->getCurrentUser()->id;

        // Someone else is already editing it. Reported as a plain false, the way the callers
        // expect - not as an exception, which would be a 500 for a routine collision. A row
        // checked out by the current user does not count as checked out.
        if ($table->isCheckedOut($userId)) {
            return false;
        }

        return $checkOut ? $table->checkOut($userId, $extensionId) : $table->checkIn($extensionId);
    }

    /**
     * Publish/unpublish/archive/trash the live `#__jed_extensions` row(s).
     *
     * Overridden because {@see getTable()} defaults to `ExtensionHistoryTable`
     * (needed for the item edit-form flow), which would make the inherited
     * `AdminModel::publish()` target history rows instead of the live table.
     *
     * @param array|int $pks   The extension id(s) to change state for.
     * @param int       $value The new state value.
     *
     * @return bool
     *
     * @throws Exception If the underlying table fails to save.
     *
     * @since 4.1.0
     */
    public function publish(&$pks, $value = 1)
    {
        $user  = $this->getCurrentUser();
        $table = $this->getTable('Extension');
        $table->setUseExceptions(true);
        $pks   = (array) $pks;

        foreach ($pks as $i => $pk) {
            $table->reset();

            if ($table->load($pk) && !$this->canEditState($table)) {
                unset($pks[$i]);
            }
        }

        if (!\count($pks)) {
            return true;
        }

        $table->publish($pks, $value, $user->id);

        return true;
    }

    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A \JForm object on success, false on failure
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form|bool
    {
        // Get the form.
        $form = $this->loadForm('com_jed.extension.' . $formname, 'extension', ['control' => $formname, 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Load all images for an extension from #__jed_extensions_images, ordered by ordering.
     *
     * @param int $extensionId The extension id to load images for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getImages(?int $extensionId = null): array
    {
        $extensionId      = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions_images'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('ordering') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load all uploaded files for an extension from #__jed_extensions_files.
     *
     * @param int $extensionId The extension id to load files for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getFiles(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions_files'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load the selected categories for an extension from #__jed_extensions_category_map.
     *
     * @param int $extensionId The extension id to load categories for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getCategories(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['map.catid', 'c.title']))
            ->from($db->quoteName('#__jed_extensions_category_map', 'map'))
            ->leftJoin(
                $db->quoteName('#__categories', 'c')
                . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('map.catid')
            )
            ->where($db->quoteName('map.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('c.title') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load the maintainers for an extension from #__jed_extensions_maintainers.
     *
     * @param int $extensionId The extension id to load maintainers for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getMaintainers(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['m.user_id', 'u.name', 'u.username']))
            ->from($db->quoteName('#__jed_extensions_maintainers', 'm'))
            ->leftJoin(
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('m.user_id')
            )
            ->where($db->quoteName('m.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('u.name') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Load all history entries for an extension from #__jed_extensions_history.
     *
     * @param int $extensionId The extension id to load history for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getHistory(?int $extensionId = null): array
    {
        $extensionId = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName('h') . '.*')
            ->select($db->quoteName('u.name', 'editor_name'))
            ->from($db->quoteName('#__jed_extensions_history', 'h'))
            ->leftJoin(
                $db->quoteName('#__users', 'u')
                . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('h.modified_by')
            )
            ->where($db->quoteName('h.extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('h.id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Block a listing, with a stated reason.
     *
     * Writes `blocked` and never `state` - the invariant the whole state model rests on (4.8).
     * If blocking touched `state`, the developer could lift the JED team's block simply by
     * republishing their listing, and the two facts would be indistinguishable afterwards.
     *
     * The reason code is mandatory and is checked against `#__jed_block_reasons`, so a block
     * always carries wording the public notice can show and the knowledge base can be keyed by.
     * The free text is optional and **internal**: it goes into the revision and never onto the
     * public site (8.7).
     *
     * @param int    $extensionId The extension id.
     * @param string $reasonCode  A code from #__jed_block_reasons.
     * @param string $reasonText  Optional internal note.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist or the reason code is not a known one.
     *
     * @since 4.1.0
     */
    public function block(int $extensionId, string $reasonCode, string $reasonText = ''): void
    {
        $reasonCode = trim($reasonCode);

        if ($reasonCode === '') {
            throw new Exception(Text::_('COM_JED_BLOCK_ERROR_REASON_REQUIRED'));
        }

        if (!$this->blockReasonExists($reasonCode)) {
            throw new Exception(Text::sprintf('COM_JED_BLOCK_ERROR_REASON_UNKNOWN', $reasonCode));
        }

        $this->applyListingState(
            $extensionId,
            [
                'blocked'           => 1,
                'block_reason_code' => $reasonCode,
                // Optional unique-ish text fields are stored as NULL, never as '' (8.14).
                'block_reason_text' => trim($reasonText) === '' ? null : trim($reasonText),
                'blocked_by'        => (int) $this->getCurrentUser()->id,
                'blocked_time'      => Factory::getDate()->toSql(),
            ]
        );
    }

    /**
     * Lift a block.
     *
     * The reason fields are kept rather than cleared: the live row would otherwise be the only
     * place that ever knew why the listing was blocked, and the revision written here is what
     * makes the block history readable. `blocked` alone decides whether the block is in force.
     *
     * @param int $extensionId The extension id.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist.
     *
     * @since 4.1.0
     */
    public function unblock(int $extensionId): void
    {
        $this->applyListingState($extensionId, ['blocked' => 0]);
    }

    /**
     * Soft-delete a listing.
     *
     * Removes it from the frontend - the detail URL answers 410 from here on - while leaving the
     * row and its uploads intact, so the backend can still read it. Hard deletion of the media
     * belongs to the privacy removal in `P1-18`, not here.
     *
     * @param int $extensionId The extension id.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist.
     *
     * @since 4.1.0
     */
    public function softDelete(int $extensionId): void
    {
        $this->applyListingState(
            $extensionId,
            [
                'deleted'      => 1,
                'deleted_by'   => (int) $this->getCurrentUser()->id,
                'deleted_time' => Factory::getDate()->toSql(),
            ]
        );

        // A handover of a listing that no longer exists is meaningless, and leaving it open would
        // let it complete later against a deleted record (8.8.1). Both parties are told.
        (new TransferService($this->getDatabase()))->cancelOpenFor(
            $extensionId,
            (int) $this->getCurrentUser()->id,
            Text::_('COM_JED_TRANSFER_CANCELLED_LISTING_DELETED')
        );
    }

    /**
     * Undo a soft delete.
     *
     * @param int $extensionId The extension id.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist.
     *
     * @since 4.1.0
     */
    public function restore(int $extensionId): void
    {
        $this->applyListingState($extensionId, ['deleted' => 0, 'deleted_by' => null, 'deleted_time' => null]);
    }

    /**
     * Write a set of state columns to the live row and record the result as a revision.
     *
     * The revision is what makes block and delete history answerable - who did what, when, under
     * which code, with which internal note - without a dedicated log table, which is the shape
     * the JED team asked for. It is written with `active = 0` on purpose: `active` marks the
     * revision the moderation workflow (`P1-02`) is holding, and a block must not disturb a
     * developer's pending edit. The live row stays authoritative for the current state.
     *
     * @param int   $extensionId The extension id.
     * @param array $columns     Column => value pairs to write.
     *
     * @return void
     *
     * @throws Exception If the listing does not exist.
     *
     * @since 4.1.0
     */
    private function applyListingState(int $extensionId, array $columns): void
    {
        /** @var ExtensionTable $liveTable */
        $liveTable = $this->getTable('Extension');
        $liveTable->setUseExceptions(true);

        if (!$liveTable->load($extensionId)) {
            throw new Exception(Text::_('JLIB_APPLICATION_ERROR_NOT_EXIST'));
        }

        $liveTable->bind($columns);
        $liveTable->check();
        $liveTable->store();

        $revision = get_object_vars($liveTable);
        unset($revision['id']);
        $revision['extension_id'] = $extensionId;
        $revision['active']       = 0;

        /** @var ExtensionHistoryTable $historyTable */
        $historyTable = $this->getTable('ExtensionHistory');
        $historyTable->setUseExceptions(true);
        $historyTable->bind($revision);
        $historyTable->check();
        $historyTable->store();
    }

    /**
     * Whether a block reason code exists and is enabled.
     *
     * @param string $code The code to check.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    private function blockReasonExists(string $code): bool
    {
        $db = $this->getDatabase();

        return (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_block_reasons'))
                ->where($db->quoteName('code') . ' = :code')
                ->where($db->quoteName('state') . ' = 1')
                ->bind(':code', $code)
        )->loadResult();
    }

    /**
     * Set one history entry as active and deactivate all others for the extension.
     *
     * @param int $extensionId The extension PK in #__jed_extensions.
     * @param int $historyId   The history entry PK to activate.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function activateVersion(int $extensionId, int $historyId): void
    {
        $db = $this->getDatabase();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 0')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 1')
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':id', $historyId, ParameterType::INTEGER)
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * Load all review entries for an extension from #__jed_reviews.
     *
     * @param int $extensionId The extension id to load reviews for.
     *
     * @return array
     *
     * @since 4.0.0
     */
    public function getReviews(?int $extensionId = null): array
    {
        $extensionId      = (!empty($extensionId)) ? $extensionId : (int) $this->getState($this->getName() . '.id');

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_reviews'))
            ->where($db->quoteName('extension_id') . ' = :extensionId')
            ->bind(':extensionId', $extensionId, ParameterType::INTEGER)
            ->order($db->quoteName('id') . ' ASC');

        return $db->setQuery($query)->loadObjectList() ?: [];
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param string $name    The table type to instantiate
     * @param string $prefix  A prefix for the table class name. Optional.
     * @param array  $options Configuration array for model. Optional.
     *
     * @return Table    A database object
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'ExtensionHistory', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @since  4.0.0
     * @throws Exception
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.extension.data', []);

        if (empty($data)) {
            return $this->getItem();
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param array $data The form data.
     *
     * @return bool  True on success, False on error.
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    public function save($data): bool
    {
        // The extension id is tracked explicitly in the session by
        // ExtensionController::edit()/add() - the same pattern the site side's
        // ExtensionformController/ExtensionformModel already use. Model::getState() alone isn't
        // reliable here because getTable() intentionally returns the history table rather than
        // the live #__jed_extensions table, so the framework's generic id bookkeeping doesn't apply.
        $extensionId = (int) Factory::getApplication()->getUserState('com_jed.edit.extension.live_id');

        if (!$extensionId) {
            // No live row yet: create one first, so we have an id to attach the history entry to.
            $extensionId = $this->createExtension($data);

            if (!$extensionId) {
                return false;
            }

            Factory::getApplication()->setUserState('com_jed.edit.extension.live_id', $extensionId);
        }

        $categories = (array) ($data['categories'] ?? []);

        // Force a new INSERT rather than an UPDATE of an existing history entry
        $data['id']           = 0;
        $data['extension_id'] = $extensionId;
        $data['active']       = 1;
        unset($data['created']); // ExtensionHistoryTable::bind() sets this for new rows

        // Only one history entry may be active per extension; deactivate the rest
        // before inserting the new (active) one.
        $db = $this->getDatabase();
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__jed_extensions_history'))
                ->set($db->quoteName('active') . ' = 0')
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
        )->execute();

        $table = $this->getTable();
        $table->setUseExceptions(true);

        $table->bind($data);
        $table->check();
        $table->store();

        // The live row is intentionally NOT advanced here: every save is a pending
        // review, only ExtensionModel::approve() writes to #__jed_extensions.

        // deleteImages/deleteFiles are plain checkboxes, not declared form fields, so they were
        // stripped by Form::filter() in AdminModel::validate() before $data reached us here.
        // Read them straight from the raw request instead.
        $rawPost = (array) Factory::getApplication()->getInput()->post->get('jform', [], 'array');

        $this->storeCategories($extensionId, $categories);
        $this->storeMaintainers($extensionId, (array) ($data['maintainer'] ?? []));
        $this->storeTags($extensionId, (array) ($data['tags'] ?? []));
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteImages'] ?? []), '#__jed_extensions_images');
        $this->deleteMarkedUploads($extensionId, (array) ($rawPost['deleteFiles'] ?? []), '#__jed_extensions_files');
        $this->storeUploadedImages($extensionId, (array) ($data['images'] ?? []));
        $this->storeUploadedFiles($extensionId, (array) ($data['files'] ?? []));

        // Keep state pointing to the extension ID (not the new history entry's PK)
        $this->setState($this->getName() . '.id', $extensionId);

        return true;
    }

    /**
     * Create the live #__jed_extensions row for a brand new extension (state.id is still 0).
     *
     * @param array $data The submitted form data.
     *
     * @return int The new #__jed_extensions.id, or 0 on failure (an error is set on the model).
     *
     * @since 4.0.0
     */
    private function createExtension(array $data): int
    {
        $user = Factory::getApplication()->getIdentity();

        /** @var ExtensionTable $table */
        $table = $this->getTable('Extension');
        $table->setUseExceptions(true);

        $liveData = [
            'id'    => 0,
            'name'  => (string) ($data['name'] ?? ''),
            'alias' => (string) ($data['alias'] ?? ''),
            'catid' => !empty($data['catid']) ? (int) $data['catid'] : null,
            'owner' => !empty($data['owner']) ? (int) $data['owner'] : (int) $user->id,
            'state' => 0,
        ];

        $table->save($liveData);

        $extensionId = (int) $table->id;

        $this->setState($this->getName() . '.id', $extensionId);

        return $extensionId;
    }
}
