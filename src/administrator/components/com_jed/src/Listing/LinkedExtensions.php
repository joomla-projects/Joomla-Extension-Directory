<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Listing;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use UnexpectedValueException;

/**
 * The two linked-extension relations of `P1-23`, and the rules that hold them together.
 *
 * There are two, they are different, and the difference is the whole point:
 *
 * | Relation        | Column          | Shape        | Meaning                                  |
 * | --------------- | --------------- | ------------ | ---------------------------------------- |
 * | Free/paid variant | `variant_of_id` | symmetric  | "Foobar Lite" and "Foobar Pro" are one product in two forms |
 * | Parent extension  | `parent_id`     | many-to-one | "this add-on extends that product"       |
 *
 * Single columns rather than the two mapping tables 8.5 called for: `P0-03` measured
 * MAX(rows per extension) = 1 on both JED3 tables, so the stock fits losslessly and an m:n
 * table would only add a way for the data to contradict itself.
 *
 * **Who may set what** is the question `P1-23` work item 4 left open, and it is answered
 * differently for the two relations because the two assertions are not comparable:
 *
 * - `variant_of_id` says something about **two listings the same vendor publishes**. A
 *   developer may set it freely, as long as they own or maintain *both* sides (8.8). If the
 *   other side belongs to somebody else, only the JED team may make the link - "not always"
 *   the same vendor, as the plan notes, and a free listing pointing at a stranger's paid one
 *   is a claim that stranger never made.
 * - `parent_id` says something about **somebody else's product**. Left unrestricted it is a
 *   traffic lever: 268 listings already point at VirtueMart, and anything that appears on
 *   VirtueMart's page rides on VirtueMart's audience. So it is split in two, exactly the way
 *   `blocked` is split from `state` (4.8):
 *     - the developer's claim lives in `parent_id` and renders on **their own** page
 *       ("Add-on for VirtueMart") - describing your own product is not a privilege;
 *     - the reverse direction, the add-on appearing on **the parent's** page, requires
 *       `parent_confirmed = 1`, which only the JED team sets.
 *   Hence "developer-proposed, team-confirmed", the third of the plan's three options, at the
 *   cost of one tinyint. Switching to "team-set" means dropping `parent_id` from the two
 *   frontend forms; switching to "developer-set" means dropping `parent_confirmed` from the
 *   reverse-direction query in the site model. Nothing else reads them.
 *
 * The validation below runs on the **Table**, not in a model, for the reason
 * `ExtensionTable::normaliseVideo()` gives: every save path leads through the tables - the
 * backend form, the frontend form, the moderation copy in `ExtensionModel::approve()` - and a
 * rule enforced anywhere else is a rule some third path does not have. Client-side checks
 * never stand in for this (4.9).
 *
 * @since 4.0.0
 */
final class LinkedExtensions
{
    /**
     * How many listings the reverse directions render inline before linking to the full list.
     *
     * VirtueMart has 268 add-ons. Rendering them all onto its detail page would bury the
     * listing itself, so the page shows a slice and hands the rest to the extensions view.
     *
     * @var int
     *
     * @since 4.0.0
     */
    public const INLINE_LIMIT = 12;

    /**
     * Reduce a submitted link value to an extension id, or null.
     *
     * Accepts what a developer actually has to hand: the numeric id, the listing's alias, or
     * the JED URL they are looking at - "the extension I extend" is something people identify
     * by its page, not by a primary key. Anything unrecognised is an error rather than a
     * silent null: quietly dropping a link the developer typed is how a form teaches people
     * that it does not work.
     *
     * Empty means NULL, never 0 (8.14). 0 is not an extension id, and JED3's own tables used
     * it as "unset" - which is why `P0-03` had to count `ucm_content_id = 0` rows to find out
     * how much of the relation was real.
     *
     * @param DatabaseInterface $db      The database driver.
     * @param mixed             $value   The submitted value: id, alias or JED URL.
     * @param string            $context Which relation this is, for the error message:
     *                                   'PARENT' or 'VARIANT'.
     *
     * @return int|null  The resolved extension id, or null when nothing was given.
     *
     * @throws UnexpectedValueException  When a value was given but names no listing.
     *
     * @since 4.0.0
     */
    public static function resolve(DatabaseInterface $db, mixed $value, string $context): ?int
    {
        if (\is_array($value) || \is_object($value)) {
            throw new UnexpectedValueException(
                Text::_('COM_JED_EXTENSION_LINK_' . $context . '_NOT_FOUND')
            );
        }

        $raw = trim((string) $value);

        if ($raw === '' || $raw === '0') {
            return null;
        }

        // A pasted URL: take the id from ...&id=123 if it carries one, otherwise the last path
        // segment, which is where the SEF alias sits.
        if (preg_match('~^https?://~i', $raw)) {
            $query = [];
            parse_str((string) parse_url($raw, PHP_URL_QUERY), $query);

            if (!empty($query['id'])) {
                $raw = (string) $query['id'];
            } else {
                $path = trim((string) parse_url($raw, PHP_URL_PATH), '/');
                $raw  = $path === '' ? '' : (string) substr(strrchr('/' . $path, '/'), 1);
            }
        }

        // "123-some-alias" is Joomla's own id-alias form and shows up in pasted URLs.
        if (preg_match('~^(\d+)-~', $raw, $m)) {
            $raw = $m[1];
        }

        if ($raw === '') {
            throw new UnexpectedValueException(
                Text::_('COM_JED_EXTENSION_LINK_' . $context . '_NOT_FOUND')
            );
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('id'))
            ->from($db->quoteName('#__jed_extensions'));

        if (ctype_digit($raw)) {
            $id = (int) $raw;
            $query->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER);
        } else {
            $query->where($db->quoteName('alias') . ' = :alias')
                ->bind(':alias', $raw, ParameterType::STRING);
        }

        $found = $db->setQuery($query, 0, 1)->loadResult();

        if ($found === null) {
            throw new UnexpectedValueException(
                Text::_('COM_JED_EXTENSION_LINK_' . $context . '_NOT_FOUND')
            );
        }

        return (int) $found;
    }

    /**
     * The display form of a stored link, for the edit forms.
     *
     * The alias, because that is what {@see resolve()} takes back and what the developer sees
     * in the URL. A bare id in a text box tells them nothing about whether it is the right one.
     *
     * @param DatabaseInterface $db The database driver.
     * @param int|null          $id The stored extension id.
     *
     * @return string  The alias, or '' when there is no link.
     *
     * @since 4.0.0
     */
    public static function displayValue(DatabaseInterface $db, ?int $id): string
    {
        if (!$id) {
            return '';
        }

        return (string) $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('alias'))
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $id, ParameterType::INTEGER)
        )->loadResult();
    }

    /**
     * Reject a link target that must not be stored.
     *
     * Three ways a target can be wrong, all of them present in the JED3 stock `P0-03` looked
     * at, which is why none of them is theoretical:
     *
     *  - **itself** - 18 free/paid rows and 6 parent rows point at their own extension. Left in,
     *    the detail page advertises the listing to itself.
     *  - **gone** - one dangling target in each table, pointing at an extension that no longer
     *    exists. A relation to a row that is not there is not a relation.
     *  - **soft-deleted** - the frontend is done with those rows (`P1-01`), so a link to one is
     *    a link to a 410.
     *
     * A blocked or offline target is *not* rejected here. That is a state, not a mistake, and
     * it can change back; it is the rendering that has to respect it, which the site model does
     * through the one shared visibility rule.
     *
     * @param DatabaseInterface $db      The database driver.
     * @param int|null          $target  The resolved target id.
     * @param int               $selfId  The extension the link belongs to (0 for a new one).
     * @param string            $context 'PARENT' or 'VARIANT', for the error message.
     *
     * @return void
     *
     * @throws UnexpectedValueException  When the target may not be linked to.
     *
     * @since 4.0.0
     */
    public static function assertLinkable(DatabaseInterface $db, ?int $target, int $selfId, string $context): void
    {
        if ($target === null) {
            return;
        }

        if ($selfId > 0 && $target === $selfId) {
            throw new UnexpectedValueException(
                Text::_('COM_JED_EXTENSION_LINK_' . $context . '_SELF')
            );
        }

        $alive = $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :id')
                ->where($db->quoteName('deleted') . ' = 0')
                ->bind(':id', $target, ParameterType::INTEGER)
        )->loadResult();

        if (!$alive) {
            throw new UnexpectedValueException(
                Text::_('COM_JED_EXTENSION_LINK_' . $context . '_NOT_FOUND')
            );
        }
    }

    /**
     * Whether the current user may declare `$target` a variant of `$selfId`.
     *
     * The team may always. A developer may when both sides are theirs - which is the ordinary
     * case, a vendor pairing their own Lite and Pro listings - and may not when the other side
     * belongs to somebody else, because that is a statement about a product they do not publish.
     *
     * @param int $selfId The listing being edited.
     * @param int $target The listing it is being paired with.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public static function mayLinkVariant(int $selfId, int $target): bool
    {
        $user = Factory::getApplication()->getIdentity();

        if ($user && $user->authorise('core.edit', 'com_jed')) {
            return true;
        }

        return self::isOwnerOrMaintainer($selfId) && self::isOwnerOrMaintainer($target);
    }

    /**
     * Owner or accepted maintainer (8.8) - never `created_by`.
     *
     * Duplicated from the site helper rather than called through it because this class runs on
     * both sides of the component and the site helper is not loadable from the backend. The
     * columns it reads are the ones that matter: `owner`, and an accepted maintainer row.
     *
     * @param int $extensionId The listing.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    private static function isOwnerOrMaintainer(int $extensionId): bool
    {
        $user   = Factory::getApplication()->getIdentity();
        $userId = (int) ($user->id ?? 0);

        if ($userId <= 0 || $extensionId <= 0) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $isOwner = $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions'))
                ->where($db->quoteName('id') . ' = :eid')
                ->where($db->quoteName('owner') . ' = :uid')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
        )->loadResult();

        if ($isOwner) {
            return true;
        }

        // JedHelper::MAINTAINER_ACCEPTED. Repeated as a literal because that constant lives in
        // the site helper and nothing in the backend loads the site namespace. An invitation
        // that has not been accepted (state 0) grants nothing.
        $accepted = 1;

        return (bool) $db->setQuery(
            $db->getQuery(true)
                ->select('1')
                ->from($db->quoteName('#__jed_extensions_maintainers'))
                ->where($db->quoteName('extension_id') . ' = :eid')
                ->where($db->quoteName('user_id') . ' = :uid')
                ->where($db->quoteName('state') . ' = :state')
                ->bind(':eid', $extensionId, ParameterType::INTEGER)
                ->bind(':uid', $userId, ParameterType::INTEGER)
                ->bind(':state', $accepted, ParameterType::INTEGER)
        )->loadResult();
    }
}
