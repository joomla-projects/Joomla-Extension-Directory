<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Ticket;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * Handler for `TicketType::Abandonware` tickets (`P1-19`).
 *
 * Unlike {@see ExtensionTicketHandler} and {@see ReviewTicketHandler}, this reads the database
 * directly instead of borrowing com_abandonware's model. That is deliberate. com_tickets already
 * depends on com_jed, which is unavoidable - a ticket about a listing has to be able to show the
 * listing - but a second such dependency, on a component that may not be installed in a given
 * deployment, would make the ticket list fatal rather than degraded when it is missing. Reading
 * two tables through the connector com_tickets already has costs nothing and cannot fail that way.
 *
 * The single action is a link into the case. Everything the team does to a case - contacting the
 * owner, marking, resolving - runs through
 * {@see \Jed\Component\Abandonware\Administrator\Service\CaseService}, and reproducing any of it
 * as a ticket button would be a second write path to an invariant that only holds because there is
 * one.
 *
 * @since 4.0.0
 */
final class AbandonwareTicketHandler implements TicketTypeHandlerInterface
{
    /**
     * @param DatabaseInterface $db The database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public static function type(): TicketType
    {
        return TicketType::Abandonware;
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterData(int $linkedItemId): ?object
    {
        try {
            $case = $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select('*')
                    ->from($this->db->quoteName('#__jed_abandonware_cases'))
                    ->where($this->db->quoteName('id') . ' = :id')
                    ->bind(':id', $linkedItemId, ParameterType::INTEGER)
            )->loadObject();
        } catch (Throwable $e) {
            // com_abandonware is not installed in this deployment.
            return null;
        }

        if ($case === null) {
            return null;
        }

        $signals = json_decode((string) ($case->signals ?? ''), true);

        $case->decoded_signals = \is_array($signals) ? $signals : [];
        $case->report_count    = (int) $this->db->setQuery(
            $this->db->getQuery(true)
                ->select('COUNT(*)')
                ->from($this->db->quoteName('#__jed_abandonware_reports'))
                ->where($this->db->quoteName('case_id') . ' = :cid')
                ->bind(':cid', $linkedItemId, ParameterType::INTEGER)
        )->loadResult();

        return $case;
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getMasterDataLayout(): string
    {
        return 'ticket.masterdata_abandonware';
    }

    /**
     * @inheritDoc
     *
     * @since 4.0.0
     */
    public function getActions(int $linkedItemId, User $user): array
    {
        if (!$user->authorise('core.edit', 'com_abandonware')) {
            return [];
        }

        return [
            new TicketAction(
                label: 'COM_TICKETS_ACTION_OPEN_ABANDONWARE_CASE',
                task: 'case.edit',
                icon: 'pencil-2',
                hiddenFields: ['cid[]' => $linkedItemId],
                option: 'com_abandonware'
            ),
        ];
    }
}
