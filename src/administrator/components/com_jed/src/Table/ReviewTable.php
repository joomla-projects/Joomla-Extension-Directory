<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\ParameterType;

/**
 * Review table
 *
 * @since 4.0.0
 */
class ReviewTable extends Table
{
    /**
     * Constructor
     *
     * @param DatabaseDriver $db A database connector object
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_jed.review';
        parent::__construct('#__jed_reviews', 'id', $db);
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     *
     * @since 4.0.0
     */
    protected function _getAssetName(): string
    {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param array|object $src    An associative array or object to bind to the Table instance.
     * @param array|string $ignore An optional array or space separated list of properties to ignore while binding.
     *
     * @return bool  True on success.
     *
     * @see    Table:bind
     * @throws Exception
     * @since  4.0.0
     */
    public function bind($src, $ignore = '')
    {
        $date = Factory::getDate();
        $app  = Factory::getApplication();

        // Support for multiple or not foreign key field: extension_id
        if (!empty($src['extension_id'])) {
            if (is_array($src['extension_id'])) {
                $src['extension_id'] = implode(',', $src['extension_id']);
            }
        } else {
            $src['extension_id'] = 0;
        }

        // Support for alias field: alias
        if (empty($src['alias'])) {
            if (empty($src['title'])) {
                $src['alias'] = OutputFilter::stringURLSafe(date('Y-m-d H:i:s'));
            } else {
                if ($app->get('unicodeslugs') == 1) {
                    $src['alias'] = OutputFilter::stringURLUnicodeSlug(trim((string) $src['title']));
                } else {
                    $src['alias'] = OutputFilter::stringURLSafe(trim((string) $src['title']));
                }
            }
        }


        // Support for checkbox field: flagged
        if (!isset($src['flagged'])) {
            $src['flagged'] = 0;
        }

        // Support for checkbox field: published
        if (!isset($src['published'])) {
            $src['published'] = 0;
        }

        if ($src['id'] == 0) {
            $src['created_on'] = $date->toSql();
        }

        if ($src['id'] == 0 && empty($src['created_by'])) {
            $src['created_by'] = Factory::getApplication()->getIdentity()->id;
        }

        return parent::bind($src, $ignore);
    }

    /**
     * Overloaded check function
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function check(): bool
    {
        // If there is an ordering column and this is a new row then get the next ordering value
        if (property_exists($this, 'ordering') && $this->id == 0) {
            $this->ordering = self::getNextOrder();
        }

        // Check if alias is unique
        if (!$this->isUnique('alias')) {
            $count        = 0;
            $currentAlias = $this->alias;
            while (!$this->isUnique('alias')) {
                $this->alias = $currentAlias . '-' . $count++;
            }
        }

        return parent::check();
    }

    /**
     * Get the type alias for the history table
     *
     * @return string  The alias as described above
     *
     * @since 4.0.0
     */
    public function getTypeAlias(): string
    {
        return $this->typeAlias;
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * Overridden to enqueue an `extension.score_recalc` job whenever this save changes
     * the row's published-ness (e.g. an admin editing a single review's status field
     * and clicking Save, rather than using the list "publish" toolbar action, which
     * goes through {@see \Jed\Component\Jed\Administrator\Model\ReviewModel::publish()}
     * instead and is not affected by this override).
     *
     * @param bool $updateNulls True to update fields even if they are null.
     *
     * @return bool  True on success.
     *
     * @since 4.0.0
     */
    public function store($updateNulls = true): bool
    {
        $wasPublished = false;

        if ($this->id) {
            $db    = $this->getDatabase();
            $query = $db->getQuery(true)
                ->select($db->quoteName('published'))
                ->from($db->quoteName('#__jed_reviews'))
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':id', $this->id, ParameterType::INTEGER);
            $wasPublished = (int) $db->setQuery($query)->loadResult() === 1;
        }

        $result = parent::store($updateNulls);

        if ($result) {
            $nowPublished = (int) $this->published === 1;

            if ($wasPublished !== $nowPublished) {
                $queueService = new QueueService($this->getDatabase());
                $userId       = (int) (Factory::getApplication()->getIdentity()->id ?? 0);
                $queueService->enqueue('extension.score_recalc', (int) $this->extension_id, null, [], $userId);
            }
        }

        return $result;
    }

    /**
     * Check if a field is unique
     *
     * @param string $field Name of the field
     *
     * @return bool True if unique
     *
     * @since 4.0.0
     */
    private function isUnique(string $field): bool
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true);

        $query
            ->select($db->quoteName($field))
            ->from($db->quoteName($this->_tbl))
            ->where($db->quoteName($field) . ' = ' . $db->quote($this->$field))
            ->where($db->quoteName('id') . ' <> ' . (int) $this->{$this->_tbl_key});

        $db->setQuery($query);
        $db->execute();

        return $db->getNumRows() == 0;
    }
}
