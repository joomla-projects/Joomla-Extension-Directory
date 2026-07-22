<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Traits;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Site\Helper\JedHelper;
use Jed\Component\Tickets\Administrator\Enum\TicketType;
use Jed\Component\Tickets\Administrator\Model\TicketmessageModel;
use Jed\Component\Tickets\Administrator\Model\TicketModel;
use Jed\Component\Tickets\Site\Helper\TicketHelper;
use Joomla\CMS\Factory;

/**
 * Creates #__jed_tickets entries for events happening on other items (an extension submitted for
 * approval, a review flagged, a VEL report filed, ...).
 *
 * @since 4.0.0
 */
trait TicketHandlingTrait
{
    /**
     * Creates a new ticket linked to the given item, records the triggering event as the ticket's
     * first (incoming) message, and sends/stores the standard confirmation reply - the same three
     * steps the deprecated `_ReportformModel::save()` used to do by hand for VEL reports, now
     * centralised so every trigger gets identical behaviour.
     *
     * @param TicketType $type  The kind of item the ticket is about.
     * @param int        $id    The linked item's primary key (e.g. #__jed_extensions.id, #__jed_reviews.id).
     * @param string     $event A short description of the triggering event; used as the ticket subject.
     *
     * @return int The new ticket's #__jed_tickets.id, or 0 on failure.
     *
     * @since 4.0.0
     */
    public function triggerTicket(TicketType $type, int $id, string $event): int
    {
        $user  = Factory::getApplication()->getIdentity();
        $model = new TicketModel();

        $data = [
            'id'                   => 0,
            'ticket_origin'        => 0,
            'ticket_category_type' => 0,
            'ticket_subject'       => $event,
            'ticket_text'          => '<p>' . htmlspecialchars($event) . '</p>',
            'linked_item_type'     => $type->value,
            'linked_item_id'       => $id,
            'ticket_status'        => 0,
            'allocated_group'      => 0,
            'allocated_to'         => 0,
            'parent_id'            => -1,
            'state'                => 1,
            'created_by'           => $user->id,
        ];

        if (!$model->save($data)) {
            return 0;
        }

        $ticketId = (int) $model->getState($model->getName() . '.id');

        $messageModel = new TicketmessageModel();
        $messageModel->save([
            'id'                => 0,
            'ticket_id'         => $ticketId,
            'subject'           => $event,
            'message'           => '<p>' . htmlspecialchars($event) . '</p>',
            'message_direction' => 1,
            'internal'          => 0,
            'created_by'        => $user->id,
        ]);

        $confirmation = JedHelper::sendMailTemplate(TicketHelper::MAIL_TEMPLATE_TICKET_CONFIRMATION, $user);

        if ($confirmation !== null) {
            $messageModel->save([
                'id'                => 0,
                'ticket_id'         => $ticketId,
                'subject'           => $confirmation->subject,
                'message'           => $confirmation->htmlbody,
                'message_direction' => 0,
                'internal'          => 0,
                'created_by'        => -1,
            ]);
        }

        return $ticketId;
    }
}
