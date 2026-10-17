<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Model;

use Jed\Component\Tickets\Site\Model\TicketformModel;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * What the front-end ticket form lets an ordinary requester set.
 *
 * Requesters open new tickets. They must not be able to name an existing ticket, change who it
 * belongs to, or set the fields the JED team works with - whatever the form posts.
 *
 * @since 4.0.0
 */
final class TicketformModelRestrictTest extends TestCase
{
    private function restrict(array $data, int $userId): array
    {
        $class = new ReflectionClass(TicketformModel::class);
        $model = $class->newInstanceWithoutConstructor();

        return $class->getMethod('restrictToRequester')->invoke($model, $data, $userId);
    }

    public function testExistingTicketIdAndStaffFieldsAreDropped(): void
    {
        $result = $this->restrict([
            'id'               => 12,
            'ticket_subject'   => 'Help',
            'ticket_text'      => 'Please',
            'created_by'       => 999,
            'ticket_status'    => 3,
            'allocated_to'     => 5,
            'allocated_group'  => 7,
            'internal_notes'   => 'injected',
            'state'            => 1,
            'parent_id'        => 4,
            'linked_item_type' => 7,
            'linked_item_id'   => 0,
        ], 42);

        foreach (['id', 'ticket_status', 'allocated_to', 'allocated_group', 'internal_notes', 'state', 'parent_id'] as $field) {
            $this->assertArrayNotHasKey($field, $result, $field . ' must not survive');
        }

        $this->assertSame('Help', $result['ticket_subject']);
        $this->assertSame('Please', $result['ticket_text']);
    }

    public function testTheTicketBelongsToTheRequesterWhateverTheFormSaid(): void
    {
        $result = $this->restrict(['created_by' => 999, 'ticket_subject' => 'x'], 42);

        $this->assertSame(42, $result['created_by']);
    }

    public function testALinkToAnItemTypeOnlyStaffUseFallsBackToOther(): void
    {
        foreach ([8, 9, 10] as $staffType) {
            $result = $this->restrict(['linked_item_type' => $staffType, 'linked_item_id' => 77], 42);

            $this->assertSame(7, $result['linked_item_type'], 'type ' . $staffType);
            $this->assertSame(0, $result['linked_item_id'], 'type ' . $staffType);
        }
    }
}
