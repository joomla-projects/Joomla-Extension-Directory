<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\Registry\Registry;
use Joomla\CMS\Table\Table;

/**
 * Review model.
 *
 * @since 4.0.0
 */
class ReviewModel extends AdminModel
{
    /**
     * @var string  Alias to manage history control
     *
     * @since 4.0.0
     */
    public $typeAlias = 'com_jed.review';
    /**
     * @var string  The prefix to use with controller messages.
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_JED';
    /**
     * @var null  Item data
     *
     * @since 4.0.0
     */
    protected $item = null;


    /**
     * Method to get the record form.
     *
     * @param array $data     An optional array of data for the form to interogate.
     * @param bool  $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return Form|bool  A Form object on success, false on failure
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function getForm($data = [], $loadData = true, $formname = 'jform'): Form
    {
        // Get the form.
        $form = $this->loadForm(
            'com_jed.review',
            'review',
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
     * @param null $pk The id of the primary key.
     *
     * @return mixed Object on success
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function getItem($pk = null): mixed
    {

        if ($item = parent::getItem($pk)) {
            if (isset($item->params)) {
                $item->params = json_encode($item->params);
            }

            // Do any procesing on fields here if needed


            return $item;
        }
        throw new Exception(Text::_("JERROR_ALERTNOAUTHOR"), 401);
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param string $name    The table type to instantiate
     * @param string $prefix  A prefix for the table class name. Optional.
     * @param array  $options Configuration array for model. Optional.
     *
     * @return Table    A database object
     *
     * @throws Exception
     * @since  4.0.0
     */
    public function getTable($name = 'Review', $prefix = 'Administrator', $options = []): Table
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Overridden to enqueue an `extension.score_recalc` job for every extension whose
     * review published-ness actually changed - core `AdminModel::publish()` (via
     * `Table::publish()`) updates `#__jed_reviews` directly and doesn't go through
     * {@see \Jed\Component\Jed\Administrator\Table\ReviewTable::store()}, so this is
     * the hook point for the bulk publish/unpublish toolbar actions (including the
     * Review ticket's "Approve" action).
     *
     * @param array|int $pks   An array of, or a single, primary key to change.
     * @param int       $value The value of the published state.
     *
     * @return bool True on success.
     *
     * @since 4.1.0
     */
    public function publish(&$pks, $value = 1): bool
    {
        $db  = $this->getDatabase();
        $ids = array_map('intval', (array) $pks);
        $before = [];

        if ($ids !== []) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'extension_id', 'published']))
                ->from($db->quoteName('#__jed_reviews'))
                ->whereIn($db->quoteName('id'), $ids);
            $before = $db->setQuery($query)->loadObjectList('id');
        }

        $result = parent::publish($pks, $value);

        if ($result) {
            $nowPublished = (int) $value === 1;
            $extensionIds = [];

            foreach ((array) $pks as $id) {
                $old = $before[$id] ?? null;

                if ($old === null) {
                    continue;
                }

                $wasPublished = (int) $old->published === 1;

                if ($wasPublished !== $nowPublished) {
                    $extensionIds[(int) $old->extension_id] = true;
                }
            }

            $queueService = new QueueService($db);
            $userId       = (int) (Factory::getApplication()->getIdentity()->id ?? 0);

            foreach (array_keys($extensionIds) as $extensionId) {
                $queueService->enqueue('extension.score_recalc', $extensionId, null, [], $userId);
            }
        }

        return $result;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return mixed  The data for the form.
     *
     * @throws Exception
     * @since  4.0.0
     */
    protected function loadFormData(): mixed
    {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_jed.edit.review.data', []);

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        return $data;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param Table $table Table Object
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function prepareTable($table)
    {
    }
}
