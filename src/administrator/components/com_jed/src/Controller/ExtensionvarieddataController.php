<?php

/**
 * @package    JED
 *
 * @copyright  (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Utilities\ArrayHelper;

/**
 * Extensionvarieddata list controller class.
 *
 * @since  4.0.0
 */
class ExtensionvarieddataController extends AdminController
{
    /**
     * Method to clone existing Extensionvarieddata
     *
     * @return  void
     *
     * @throws  Exception
     * @since 4.0.0
     */
    public function duplicate()
    {
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', [], 'array');

        try {
            if (empty($pks)) {
                throw new Exception(Text::_('COM_JED_NO_ELEMENT_SELECTED'));
            }

            ArrayHelper::toInteger($pks);
            $model = $this->getModel();
            $model->duplicate($pks);
            $this->setMessage(Text::_('COM_JED_ITEMS_SUCCESS_DUPLICATED'));
        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_jed&view=extensionvarieddata');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object  The Model
     *
     * @since   4.0.0
     */
    public function getModel($name = 'Extensionvarieddatum', $prefix = 'Administrator', $config = []): object
    {
        return parent::getModel($name, $prefix, ['ignore_request' => true]);
    }



    /**
     * Method to save the submitted ordering values for records via AJAX.
     *
     * @return  void
     *
     * @since   4.0.0
     *
     * @throws  Exception
     */
    public function saveOrderAjax()
    {
        // Get the input
        $input = Factory::getApplication()->input;
        $pks   = $input->post->get('cid', [], 'array');
        $order = $input->post->get('order', [], 'array');

        // Sanitize the input
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        // Get the model
        $model = $this->getModel();

        // Save the ordering
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo "1";
        }

        // Close the application
        Factory::getApplication()->close();
    }
}
