<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Enum\CaseStatus;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\ParameterType;
use RuntimeException;

/**
 * One entry in the public list.
 *
 * The visibility rule is the same one the list applies, repeated here rather than inherited,
 * because a detail view reachable by guessing an id past a list that filters is the classic way an
 * unpublished record leaks. A case that is not `published` and `abandoned` is a 404 here, not a
 * blank page.
 *
 * The column set is the whole of what is public. `internal_notes`, `signals`, `contact_note`, the
 * assignee and every report are absent, and not because a template chooses not to print them.
 *
 * @since 4.0.0
 */
class CaseModel extends ItemModel
{
    /**
     * @param array $config Configuration settings.
     *
     * @since 4.0.0
     */
    protected function populateState(): void
    {
        $this->setState('case.id', Factory::getApplication()->getInput()->getInt('id', 0));
    }

    /**
     * @param int|null $pk The case id.
     *
     * @return object
     *
     * @throws RuntimeException  No such public case.
     *
     * @since 4.0.0
     */
    public function getItem($pk = null): object
    {
        $pk = (int) ($pk ?: $this->getState('case.id'));

        if ($pk <= 0) {
            throw new RuntimeException(\Joomla\CMS\Language\Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'), 404);
        }

        $db     = $this->getDatabase();
        $marked = CaseStatus::ABANDONED->value;

        $item = $db->setQuery(
            $db->getQuery(true)
                ->select(
                    [
                        $db->quoteName('a.id'),
                        $db->quoteName('a.extension_id'),
                        $db->quoteName('a.extension_name'),
                        $db->quoteName('a.extension_version'),
                        $db->quoteName('a.extension_url'),
                        $db->quoteName('a.developer_name'),
                        $db->quoteName('a.abandoned_time'),
                        $db->quoteName('e.alias', 'listing_alias'),
                        $db->quoteName('e.approved', 'listing_approved'),
                        $db->quoteName('e.state', 'listing_state'),
                        $db->quoteName('e.blocked', 'listing_blocked'),
                        $db->quoteName('e.deleted', 'listing_deleted'),
                    ]
                )
                ->from($db->quoteName('#__jed_abandonware_cases', 'a'))
                ->leftJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('a.extension_id'))
                ->where($db->quoteName('a.id') . ' = :id')
                ->where($db->quoteName('a.published') . ' = 1')
                ->where($db->quoteName('a.status') . ' = :marked')
                ->bind(':id', $pk, ParameterType::INTEGER)
                ->bind(':marked', $marked)
        )->loadObject();

        if ($item === null) {
            throw new RuntimeException(\Joomla\CMS\Language\Text::_('COM_ABANDONWARE_ERROR_CASE_NOT_FOUND'), 404);
        }

        return $item;
    }
}
