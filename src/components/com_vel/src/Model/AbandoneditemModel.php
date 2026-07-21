<?php

/**
 * @package VEL
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel as BaseItemModel;
use Joomla\CMS\Uri\Uri;

/**
 * VEL Abandoned Item Model Class.
 *
 * Read-only single-item view of a #__vel_abandoned row. Only rows with state=1 are
 * ever visible here - there is no create/edit/delete path in this model.
 *
 * @since 4.0.0
 */
class AbandoneditemModel extends BaseItemModel
{
    /**
     * The item object
     *
     * @var   object
     * @since 4.0.0
     */
    public mixed $item;

    /**
     * Method to get a single Abandoned Item.
     *
     * @param null $pk The id of the object to get.
     *
     * @return object|bool Object on success, false on failure.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function getItem($pk = null): object|bool
    {
        if (!isset($this->item)) {
            $this->item = false;

            if (empty($pk)) {
                $pk = $this->getState('item.id');
            }

            try {
                // Get a db connection.
                $db = Factory::getContainer()->get('DatabaseDriver');

                // Create a new query object.
                $query = $db->getQuery(true);

                // Get from #__vel_abandoned as a
                $query->select(
                    $db->quoteName(
                        [
                            'a.id',
                            'a.reporter_fullname',
                            'a.extension_name',
                            'a.developer_name',
                            'a.extension_version',
                            'a.extension_url',
                            'a.abandoned_reason',
                            'a.date_submitted',
                            'a.state',
                        ]
                    )
                );
                $query->from($db->quoteName('#__vel_abandoned', 'a'));
                $query->where('a.state = 1 AND a.id = ' . (int) $pk);

                // Reset the query using our newly populated query object.
                $db->setQuery($query);
                // Load the results as a stdClass object.
                $data = $db->loadObject();

                if (empty($data)) {
                    /* @var $app \Joomla\CMS\Application\SiteApplication */
                    $app = Factory::getApplication();
                    // If no data is found redirect to default page and show warning.
                    $app->enqueueMessage('Cannot access Abandoned Item entry', 'warning');
                    $app->redirect(Uri::root());

                    return false;
                }

                // Set data object to item.
                $this->item = $data;
            } catch (Exception $e) {
                $this->item = false;
                throw $e;
            }
        }

        return $this->item;
    }

    /**
     * Method to autopopulate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     * Note. Function not needed as model is only displayed
     *
     * @return void
     *
     * @since 4.0.0
     *
     * @throws Exception
     */
    protected function populateState(): void
    {
        $app = Factory::getApplication();

        $id = Factory::getApplication()->input->get('id');

        $this->setState('item.id', $id);

        // Load the parameters.
        $params       = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('item.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }
}
