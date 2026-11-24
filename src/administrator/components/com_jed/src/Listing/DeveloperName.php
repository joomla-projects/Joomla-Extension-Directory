<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Listing;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

/**
 * The public attribution for a listing's current owner.
 *
 * @since 4.0.0
 */
final class DeveloperName
{
    public static function forOwner(DatabaseInterface $db, int $ownerId): string
    {
        return (string) $db->setQuery(
            self::query($db)
                ->where($db->quoteName('developer_user.id') . ' = :developerId')
                ->bind(':developerId', $ownerId, ParameterType::INTEGER)
        )->loadResult();
    }

    /**
     * The same name as a scalar subquery for a listing query whose table alias is `a`.
     */
    public static function listingExpression(DatabaseInterface $db): string
    {
        return '(' . self::query($db)
            ->where($db->quoteName('developer_user.id') . ' = ' . $db->quoteName('a.owner')) . ')';
    }

    private static function query(DatabaseInterface $db): QueryInterface
    {
        // The public catalogue and index use a public, published field value. The aggregate
        // keeps one name per owner even if an imported field has several saved values.
        $value      = $db->quoteName('developer_value.value');
        $customName = $db->createQuery();
        // Match Joomla's string item ID using the column's collation.
        $ownerKey = $customName->concatenate([$db->quote(''), $db->quoteName('developer_user.id')]);
        $customName
            ->select('MAX(NULLIF(TRIM(' . $value . '), ' . $db->quote('') . '))')
            ->from($db->quoteName('#__fields', 'developer_field'))
            ->innerJoin(
                $db->quoteName('#__fields_values', 'developer_value'),
                $db->quoteName('developer_value.field_id') . ' = ' . $db->quoteName('developer_field.id')
            )
            ->where($db->quoteName('developer_field.context') . ' = ' . $db->quote('com_users.user'))
            ->where($db->quoteName('developer_field.name') . ' = ' . $db->quote('developer_name'))
            ->where($db->quoteName('developer_field.state') . ' = 1')
            ->where($db->quoteName('developer_field.access') . ' = 1')
            ->where($db->quoteName('developer_value.item_id') . ' = ' . $ownerKey);

        return $db->createQuery()
            ->select('COALESCE((' . $customName . '), ' . $db->quoteName('developer_user.name') . ')')
            ->from($db->quoteName('#__users', 'developer_user'));
    }
}
