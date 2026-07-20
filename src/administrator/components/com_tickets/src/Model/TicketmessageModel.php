<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Tickets\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;

// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * JED Ticket Message model.
 *
 * @since 4.0.0
 */
class TicketmessageModel extends AdminModel
{
    /**
     * @var   string    Alias to manage history control
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.ticketmessage';
    /**
     * @var   string    The prefix to use with controller messages.
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';
    /**
     * @var   mixed  Item data
     * @since 4.0.0
     */
    protected mixed $item = null;

    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A Form object on success, false on failure
     *
     * @since 4.0.0
     *
     * @throws
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form|bool
    {
        $form = $this->loadForm(
            'com_jed.ticketmessage',
            'ticketmessage',
            [
            'control'   => $formname,
            'load_data' => $loadData,
            ]
        );


        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get a single record.
     *
     * @param int $pk The id of the primary key.
     *
     * @return mixed    Object on success, false on failure.
     *
     * @since 4.0.0
     */
    public function getItem($pk = null): mixed
    {
        return parent::getItem($pk);
    }

    /**
     * Removes the "internal" field for users who don't have core.manage on com_tickets - only
     * managers may flag a ticket message as internal-only.
     *
     * @param Form   $form  The form to process.
     * @param mixed  $data  The data to bind to the form.
     * @param string $group The name of the plugin group to import.
     *
     * @return void
     *
     * @since 4.0.0
     * @throws Exception
     */
    protected function preprocessForm(Form $form, $data, $group = 'content'): void
    {
        parent::preprocessForm($form, $data, $group);

        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_tickets')) {
            $form->removeField('internal');
        }
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param string $name
     * @param string $prefix  A prefix for the table class name. Optional.
     * @param array  $options
     *
     * @return Table    A database object
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getTable($name = 'Ticketmessage', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @since 4.0.0
     *
     * @throws
     */
    protected function loadFormData(): mixed
    {
        $data = Factory::getApplication()->getUserState('com_jed.edit.ticketmessage.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        return $data;
    }
}
