<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;

/**
 * Ticket Helper
 *
 * Builds the data structures for the ticket types created automatically from other parts of the
 * site (review reports, extension reports, VEL reports).
 *
 * @package JED
 * @since   1.0.0
 */
class TicketHelper
{
    /**
     * Mail template key (#__mail_templates.template_id) of the automated "thank you for
     * contacting us" confirmation. Must match the key seeded by
     * administrator/components/com_tickets/script.php::MAIL_TEMPLATE_TICKET_CONFIRMATION.
     *
     * @var   string
     * @since 1.0.0
     */
    public const MAIL_TEMPLATE_TICKET_CONFIRMATION = 'com_jed.ticket_confirmation';

    /**
     * For a new review this creates a corresponding Ticket
     *
     * @param int $item_id Reference for stored report
     *
     * @return array  Ticket Template
     * @since  1.0.0
     *
     * @throws Exception
     */
    public static function createReviewTicket(int $item_id): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $ticket = [];

        $user = Factory::getApplication()->getIdentity();

        $ticket['id']               = 0;
        $ticket['created_by']       = $user->id;
        $ticket['modified_by']      = $user->id;
        $ticket['created_on']       = 'now()';
        $ticket['modified_on']      = 'now()';
        $ticket['state']            = 0;
        $ticket['ordering']         = 0;
        $ticket['checked_out']      = 0;
        $ticket['checked_out_time'] = '0000-00-00 00:00:00';
        $ticket['ticket_origin']    = 0; //Registered User


        $ticket['ticket_category_type'] = 3;
        $ticket['ticket_subject']       = "A new Review";
        $ticket['linked_item_type']     = 3;     //    Review


        /*
            Ticket Category type

           <option value="1">Unknown</option>
           <option value="2">Extension</option>
           <option value="3">Review</option>
           <option value="4">Joomla Site Issue</option>
           <option value="5">New Listing Support</option>
           <option value="6">Current Listing Support</option>
           <option value="7">Site Technical Issues</option>
           <option value="8">Unpublished Support</option>
           <option value="9">Reported Review</option>
           <option value="10">Reported Extension</option>
           <option value="11">Vulnerable Item Report</option>
           <option value="12">VEL Developer Update</option>
           <option value="13">VEL Abandonware Report</option>*/


        $ticket['allocated_group'] = 4; //Assign to review Team
        /* Alloc Groups
            1 - Any
            2 - Team Leadership
            3 - Listing Specialist
            4 - Review Specialist
            5 - Support Specialist
            6 - VEL Specialist */

        $ticket['linked_item_id'] = $item_id;

        /* Linked Item Types
         <option value="1" selected="selected">Unknown</option>
         <option value="2">Extension</option>
         <option value="3">Review</option>
         <option value="4">Vulnerable Item Initial Report</option>
         <option value="5">Vulnerable Item Developer Update</option>
         <option value="6">Abandonware Report</option>
        //       <option value="7">Vulnerable Item Email Correspondence</option> */


        $ticket['ticket_status'] = 0; //New
        /*
            <option value="0" selected="selected">New</option>
            <option value="1">Awaiting User</option>
            <option value="2">Awaiting JED</option>
            <option value="3">Resolved</option>
            <option value="4">Closed</option>
            <option value="5">Updated</option>

        */
        $ticket['ticket_text']    = '<p>Please see linked review</p>';
        $ticket['internal_notes'] = '';

        $ticket['uploaded_files_preview']  = '';
        $ticket['uploaded_files_location'] = '';
        $ticket['allocated_to']            = 0;
        $ticket['parent_id']               = -1;


        foreach ($ticket as $k => $v) {
            if (str_ends_with($k, "_on")) {
                $ticket[$k] = $v;
            } else {
                $ticket[$k] = $db->quote($v);
            }
        }

        return $ticket;
    }


    /**
     * For a new Extension this creates a corresponding Ticket
     *
     * @param int $item_id Reference for extension
     *
     * @return array  Ticket Template
     * @since  1.0.0
     *
     * @throws Exception
     */
    public static function createExtensionTicket(int $item_id): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $ticket = [];

        $user = Factory::getApplication()->getIdentity();

        $ticket['id']               = 0;
        $ticket['created_by']       = $user->id;
        $ticket['modified_by']      = $user->id;
        $ticket['created_on']       = 'now()';
        $ticket['modified_on']      = 'now()';
        $ticket['state']            = 0;
        $ticket['ordering']         = 0;
        $ticket['checked_out']      = 0;
        $ticket['checked_out_time'] = '0000-00-00 00:00:00';
        $ticket['ticket_origin']    = 0; //Registered User


        $ticket['ticket_category_type'] = 2;
        $ticket['ticket_subject']       = "A new Extension";
        $ticket['linked_item_type']     = 2;     //    Extension


        /*
            Ticket Category type

           <option value="1">Unknown</option>
           <option value="2">Extension</option>
           <option value="3">Review</option>
           <option value="4">Joomla Site Issue</option>
           <option value="5">New Listing Support</option>
           <option value="6">Current Listing Support</option>
           <option value="7">Site Technical Issues</option>
           <option value="8">Unpublished Support</option>
           <option value="9">Reported Review</option>
           <option value="10">Reported Extension</option>
           <option value="11">Vulnerable Item Report</option>
           <option value="12">VEL Developer Update</option>
           <option value="13">VEL Abandonware Report</option>*/


        $ticket['allocated_group'] = 3; //Assign to review Team
        /* Alloc Groups
            1 - Any
            2 - Team Leadership
            3 - Listing Specialist
            4 - Review Specialist
            5 - Support Specialist
            6 - VEL Specialist */

        $ticket['linked_item_id'] = $item_id;

        /* Linked Item Types
         <option value="1" selected="selected">Unknown</option>
         <option value="2">Extension</option>
         <option value="3">Review</option>
         <option value="4">Vulnerable Item Initial Report</option>
         <option value="5">Vulnerable Item Developer Update</option>
         <option value="6">Abandonware Report</option>
        //       <option value="7">Vulnerable Item Email Correspondence</option> */


        $ticket['ticket_status'] = 2; //New
        /*
            <option value="0" selected="selected">New</option>
            <option value="1">Awaiting User</option>
            <option value="2">Awaiting JED</option>
            <option value="3">Resolved</option>
            <option value="4">Closed</option>
            <option value="5">Updated</option>

        */
        $ticket['ticket_text']    = '<p>Please see linked extension</p>';
        $ticket['internal_notes'] = '';

        $ticket['uploaded_files_preview']  = '';
        $ticket['uploaded_files_location'] = '';
        $ticket['allocated_to']            = 0;
        $ticket['parent_id']               = -1;


        foreach ($ticket as $k => $v) {
            $ticket[] = $k;
            if (str_ends_with($k, "_on")) {
                $ticket[] = $v;
            } else {
                $ticket[] = $db->quote($v);
            }
        }

        return $ticket;
    }

    /**
     * When a VEL is reported or a Developer Update or Abandoned Item reported  this creates a corresponding Ticket
     *
     * @param int $report_type 1 for VEL REPORT, 2 for DEVELOPER UPDATE, 3 for ABANDONWARE REPORT
     * @param int $item_id     Reference for stored report
     *
     * @return array  Ticket Template
     * @since  1.0.0
     *
     * @throws Exception
     */
    public static function createVELTicket(int $report_type, int $item_id): array
    {
        $ticket = [];

        $user = Factory::getApplication()->getIdentity();

        $ticket['id']          = 0;
        $ticket['created_by']  = $user->id;
        $ticket['modified_by'] = $user->id;
        //   $ticket['created_on']       = Factory::getDate()->format('Y-m-d H:i:s');
        // $ticket['modified_on']      = Factory::getDate()->format('Y-m-d H:i:s');
        $ticket['state']            = 0;
        $ticket['ordering']         = 0;
        $ticket['checked_out']      = 0;
        $ticket['checked_out_time'] = '0000-00-00 00:00:00';
        $ticket['ticket_origin']    = 0; //Registered User

        switch ($report_type) {
            case 1: // VEL REPORT
                $ticket['ticket_category_type'] = 11;
                $ticket['ticket_subject']       = "A new Vulnerable Item Report";
                $ticket['linked_item_type']     = 4;     //    Vulnerable Item Initial Report
                break;
            case 2: // DEVELOPER UPDATE
                $ticket['ticket_category_type'] = 12;
                $ticket['ticket_subject']       = "A new VEL Developer Update";
                $ticket['linked_item_type']     = 5;     //    Vulnerable Item Developer Update
                break;

            case 3: // ABANDONWARE REPORT
                $ticket['ticket_category_type'] = 13;
                $ticket['ticket_subject']       = "A new VEL Abandonware Report";
                $ticket['linked_item_type']     = 6;     //    Vulnerable Item Abandonware Report
                break;
        }

        /*
            Ticket Category type

           <option value="1">Unknown</option>
           <option value="2">Extension</option>
           <option value="3">Review</option>
           <option value="4">Joomla Site Issue</option>
           <option value="5">New Listing Support</option>
           <option value="6">Current Listing Support</option>
           <option value="7">Site Technical Issues</option>
           <option value="8">Unpublished Support</option>
           <option value="9">Reported Review</option>
           <option value="10">Reported Extension</option>
           <option value="11">Vulnerable Item Report</option>
           <option value="12">VEL Developer Update</option>
           <option value="13">VEL Abandonware Report</option>*/


        $ticket['allocated_group'] = 6; //These are VEL subjects
        /* Alloc Groups
            1 - Any
            2 - Team Leadership
            3 - Listing Specialist
            4 - Review Specialist
            5 - Support Specialist
            6 - VEL Specialist */

        $ticket['linked_item_id'] = $item_id;

        /* Linked Item Types
         <option value="1" selected="selected">Unknown</option>
         <option value="2">Extension</option>
         <option value="3">Review</option>
         <option value="4">Vulnerable Item Initial Report</option>
         <option value="5">Vulnerable Item Developer Update</option>
         <option value="6">Abandonware Report</option>
        //       <option value="7">Vulnerable Item Email Correspondence</option> */


        $ticket['ticket_status'] = 0; //New
        /*
            <option value="0" selected="selected">New</option>
            <option value="1">Awaiting User</option>
            <option value="2">Awaiting JED</option>
            <option value="3">Resolved</option>
            <option value="4">Closed</option>
            <option value="5">Updated</option>

        */
        $ticket['ticket_text']    = '<p>Please see linked report</p>';
        $ticket['internal_notes'] = '';

        $ticket['uploaded_files_preview']  = '';
        $ticket['uploaded_files_location'] = '';
        $ticket['allocated_to']            = 0;
        $ticket['parent_id']               = -1;

        /* foreach ($ticket as $k => $v) {
             if (str_ends_with($k, "_on")) {
                 $ticket[$k] = $v;
             } else {
                 $ticket[$k] = $db->quote($v);
             }
         }*/

        return $ticket;
    }

    /**
     * Create Empty Ticket Message
     *
     * @return array
     *
     * @since  1.0.0
     * @throws Exception
     */
    public static function createEmptyTicketMessage(): array
    {
        $user                          = Factory::getApplication()->getIdentity();
        $ticket_message                = [];
        $ticket_message['id']          = 0;
        $ticket_message['created_by']  = $user->id;
        $ticket_message['modified_by'] = $user->id;
        //    $ticket_message['created_on']       = 'now()';
        $ticket_message['state']            = 0;
        $ticket_message['ordering']         = 0;
        $ticket_message['checked_out']      = 0;
        $ticket_message['checked_out_time'] = '0000-00-00 00:00:00';

        return $ticket_message;
    }
}
