<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Service;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryStatus;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Everything com_vel needs from com_jed, in one place (VEL2-PRD §6.9).
 *
 * Tolerates com_jed being absent - every method answers "nothing" rather than throwing - so that
 * the VEL still works on an installation that carries only the list. Nothing confidential ever
 * crosses this bridge: the ticket body is built from public fields only.
 *
 * @since 4.0.0
 */
class JedBridge
{
    /**
     * The TicketType value reserved for the VEL in com_jed's enum. 4, 5 and 6 were the old
     * com_vel's and stay retired; this is new (VEL2-PRD §6.9.2).
     *
     * @since 4.0.0
     */
    public const TICKET_TYPE_VULNERABILITY = 11;

    /**
     * The block reason code the JED team uses for a listing on the VEL.
     *
     * @since 4.0.0
     */
    public const BLOCK_REASON_CODE = 'VL1';

    /**
     * @param DatabaseInterface $db The database driver.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @return bool  Whether com_jed is installed and enabled here.
     *
     * @since 4.0.0
     */
    public function available(): bool
    {
        static $available = null;

        if ($available === null) {
            try {
                $available = ComponentHelper::isEnabled('com_jed');
            } catch (Throwable $e) {
                $available = false;
            }
        }

        return $available;
    }

    /**
     * Resolve whatever an editor typed - id, alias, "123-alias", a pasted JED URL - to a
     * listing id.
     *
     * @param mixed $value The typed value.
     *
     * @return int|null  The id, or null when blank.
     *
     * @throws \UnexpectedValueException  Given but names no listing.
     *
     * @since 4.0.0
     */
    public function resolveListing(mixed $value): ?int
    {
        $raw = is_scalar($value) ? trim((string) $value) : '';

        if ($raw === '' || $raw === '0') {
            return null;
        }

        if (!$this->available()) {
            return ctype_digit($raw) ? (int) $raw : null;
        }

        if (class_exists('\\Jed\\Component\\Jed\\Administrator\\Listing\\LinkedExtensions')) {
            // com_jed's own resolver: the same rules a developer's "parent listing" field uses.
            // Its refusal names a com_jed language key this context has not loaded, so it is
            // rethrown with ours.
            try {
                return \Jed\Component\Jed\Administrator\Listing\LinkedExtensions::resolve($this->db, $raw, 'VEL');
            } catch (\UnexpectedValueException $e) {
                throw new \UnexpectedValueException(Text::sprintf('COM_VEL_ERROR_LISTING_NOT_FOUND', $raw), 0, $e);
            }
        }

        if (preg_match('~^https?://~i', $raw)) {
            $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');
            $raw  = $path === '' ? '' : (string) substr(strrchr('/' . $path, '/'), 1);
        }

        if (preg_match('~^(\d+)-~', $raw, $m)) {
            $raw = $m[1];
        }

        $query = $this->db->getQuery(true)
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__jed_extensions'));

        if (ctype_digit($raw)) {
            $id = (int) $raw;
            $query->where($this->db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $query->where($this->db->quoteName('alias') . ' = :alias')->bind(':alias', $raw);
        }

        $found = $this->db->setQuery($query, 0, 1)->loadResult();

        if ($found === null) {
            throw new \UnexpectedValueException(Text::sprintf('COM_VEL_ERROR_LISTING_NOT_FOUND', $raw));
        }

        return (int) $found;
    }

    /**
     * The listing facts the editor and the vendor resolver need.
     *
     * @param int $extensionId The listing.
     *
     * @return object|null  id, name, alias, owner, developer_url, developer_email,
     *                      extension_version, state, approved, blocked, deleted.
     *
     * @since 4.0.0
     */
    public function listing(int $extensionId): ?object
    {
        if ($extensionId <= 0 || !$this->available()) {
            return null;
        }

        try {
            return $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(['id', 'name', 'alias', 'owner', 'developer_url', 'developer_email', 'extension_version', 'state', 'approved', 'blocked', 'deleted', 'extension_types']))
                    ->from($this->db->quoteName('#__jed_extensions'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $extensionId, ParameterType::INTEGER)
            )->loadObject() ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * What "copy from JED listing" fills in: vendor (the developer site's host), product (the
     * listing name), supplier URL, and the identity if com_jed has learned it.
     *
     * @param int $extensionId The listing.
     *
     * @return array<string, mixed>|null
     *
     * @since 4.0.0
     */
    public function productDefaults(int $extensionId): ?array
    {
        $listing = $this->listing($extensionId);

        if ($listing === null) {
            return null;
        }

        $host = (string) parse_url((string) $listing->developer_url, PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', $host) ?? $host;

        $defaults = [
            'extension_id' => (int) $listing->id,
            'vendor'       => $host,
            'product'      => (string) $listing->name,
            'supplier_url' => (string) $listing->developer_url,
        ];

        $identity = $this->identity($extensionId);

        if ($identity !== null) {
            $defaults += $identity;
        }

        return $defaults;
    }

    /**
     * The Joomla identity of a listing, if com_jed has a `#__jed_extension_identities` table
     * (VEL2-PRD §6.9.3, shipped separately). Null until it does.
     *
     * @param int $extensionId The listing.
     *
     * @return array{ext_type:string, element:string, folder:?string, client_id:?int}|null
     *
     * @since 4.0.0
     */
    public function identity(int $extensionId): ?array
    {
        try {
            $row = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(['ext_type', 'element', 'folder', 'client_id']))
                    ->from($this->db->quoteName('#__jed_extension_identities'))
                    ->where($this->db->quoteName('extension_id') . ' = :id')
                    ->bind(':id', $extensionId, ParameterType::INTEGER)
                    ->order($this->db->quoteName('confirmed') . ' DESC'),
                0,
                1
            )->loadAssoc();
        } catch (Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return [
            'ext_type'  => (string) $row['ext_type'],
            'element'   => (string) $row['element'],
            'folder'    => $row['folder'] !== null ? (string) $row['folder'] : null,
            'client_id' => $row['client_id'] !== null ? (int) $row['client_id'] : null,
        ];
    }

    /**
     * The user ids the vendor notification goes to: the owner and every accepted maintainer.
     * Read from `owner` and the maintainers table, never `created_by` - authorship does not
     * follow a transfer (CaseService::mailOwner()'s reasoning).
     *
     * @param int $extensionId The listing.
     *
     * @return int[]
     *
     * @since 4.0.0
     */
    public function listingContacts(int $extensionId): array
    {
        $listing = $this->listing($extensionId);

        if ($listing === null) {
            return [];
        }

        $ids = [(int) $listing->owner];

        try {
            $accepted    = 1;
            $maintainers = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName('user_id'))
                    ->from($this->db->quoteName('#__jed_extensions_maintainers'))
                    ->where($this->db->quoteName('extension_id') . ' = :eid')
                    ->where($this->db->quoteName('state') . ' = :accepted')
                    ->bind(':eid', $extensionId, ParameterType::INTEGER)
                    ->bind(':accepted', $accepted, ParameterType::INTEGER)
            )->loadColumn() ?: [];

            $ids = array_merge($ids, array_map('intval', $maintainers));
        } catch (Throwable $e) {
            // No maintainers table: the owner alone.
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Open the JED team's ticket for a published advisory that names a listing (VEL2-PRD
     * §6.9.2). Public information only: the body says what the public page says.
     *
     * @param object $advisory    The advisory with children.
     * @param int    $extensionId The listing.
     * @param string $publicUrl   The advisory's public URL.
     *
     * @return int  The ticket id, or 0 if none could be opened.
     *
     * @since 4.0.0
     */
    public function openTicket(object $advisory, int $extensionId, string $publicUrl): int
    {
        if (!$this->available()) {
            return 0;
        }

        $listing = $this->listing($extensionId);

        if ($listing === null) {
            return 0;
        }

        Factory::getApplication()->getLanguage()->load('com_vel', JPATH_ADMINISTRATOR . '/components/com_vel');

        $reference = \sprintf('VEL-%04d', (int) $advisory->id);
        $status    = AdvisoryStatus::tryFrom((string) $advisory->status) ?? AdvisoryStatus::LIVE;
        $lines     = [];

        foreach ((array) ($advisory->products ?? []) as $product) {
            if ((int) ($product->extension_id ?? 0) !== $extensionId) {
                continue;
            }

            $ranges = [];

            foreach ((array) ($product->versions ?? []) as $version) {
                $ranges[] = (string) $version->version_from . ' - ' . (string) $version->version_to;
            }

            $lines[] = (string) $product->product . ': ' . implode(', ', $ranges)
                . ((string) $product->fixed_version !== '' ? ' (' . Text::sprintf('COM_VEL_JED_TICKET_FIXED_IN', (string) $product->fixed_version) . ')' : '');
        }

        $subject = Text::sprintf('COM_VEL_JED_TICKET_SUBJECT', $reference, (string) $listing->name);
        $body    = Text::sprintf(
            'COM_VEL_JED_TICKET_BODY',
            $reference,
            (string) $advisory->title,
            Text::_($status->label()),
            implode("\n", $lines),
            $publicUrl,
            $status === AdvisoryStatus::LIVE ? Text::_('COM_VEL_JED_TICKET_ACTION_BLOCK') : Text::_('COM_VEL_JED_TICKET_ACTION_WATCH')
        );

        $ticket = (object) [
            'ticket_origin'        => 1,
            'ticket_category_type' => 0,
            'ticket_subject'       => mb_substr($subject, 0, 255),
            'ticket_text'          => nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')),
            'linked_item_type'     => self::TICKET_TYPE_VULNERABILITY,
            'linked_item_id'       => (int) $advisory->id,
            'ticket_status'        => '0',
            'allocated_group'      => 0,
            'allocated_to'         => 0,
            'parent_id'            => -1,
            'state'                => 1,
            'created_by'           => 0,
            'created_on'           => Factory::getDate()->toSql(),
        ];

        try {
            $this->db->insertObject('#__jed_tickets', $ticket, 'id');
        } catch (Throwable $e) {
            Log::add(\sprintf('Advisory %d: JED ticket could not be opened: %s', (int) $advisory->id, $e->getMessage()), Log::ERROR, 'com_vel');

            return 0;
        }

        return (int) $ticket->id;
    }

    /**
     * Note on the JED ticket that the advisory's status changed, and resolve it when there is
     * nothing left for the JED team to do.
     *
     * @param int    $ticketId The ticket.
     * @param string $note     The line.
     * @param bool   $resolve  Whether to set it Resolved.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function annotateTicket(int $ticketId, string $note, bool $resolve): void
    {
        if ($ticketId <= 0 || !$this->available()) {
            return;
        }

        $now     = Factory::getDate()->toSql();
        $stamped = "\n[" . $now . '] ' . $note;

        try {
            $query = $this->db->getQuery(true)
                ->update($this->db->quoteName('#__jed_tickets'))
                ->set($this->db->quoteName('internal_notes') . ' = CONCAT(IFNULL('
                    . $this->db->quoteName('internal_notes') . ", ''), :note)")
                ->set($this->db->quoteName('modified_on') . ' = :now')
                ->where($this->db->quoteName('id') . ' = :id')
                ->bind(':note', $stamped)
                ->bind(':now', $now)
                ->bind(':id', $ticketId, ParameterType::INTEGER);

            if ($resolve) {
                // 3 = Resolved in com_jed's ticket status vocabulary.
                $query->set($this->db->quoteName('ticket_status') . ' = ' . $this->db->quote('3'));
            }

            $this->db->setQuery($query)->execute();
        } catch (Throwable $e) {
            // The advisory is the record.
        }
    }

    /**
     * The backend URL of a listing, for links in the editor.
     *
     * @param int $extensionId The listing.
     *
     * @return string
     *
     * @since 4.0.0
     */
    public function adminListingUrl(int $extensionId): string
    {
        return 'index.php?option=com_jed&task=extension.edit&id=' . $extensionId;
    }
}
