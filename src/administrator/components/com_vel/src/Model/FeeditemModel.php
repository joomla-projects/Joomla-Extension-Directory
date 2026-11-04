<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;

/**
 * One feed item, beside the advisory it is linked to.
 *
 * @since 4.0.0
 */
class FeeditemModel extends AdminModel
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_VEL';

    /**
     * @param string $type   The table type.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return Table
     *
     * @since 4.0.0
     */
    public function getTable($type = 'FeedItem', $prefix = 'Administrator', $config = []): Table
    {
        return $this->getMVCFactory()->createTable($type, $prefix, $config);
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Whether to load the data.
     *
     * @return Form|false
     *
     * @since 4.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return false;
    }

    /**
     * @param int|null $pk The item id.
     *
     * @return object|false
     *
     * @since 4.0.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->id)) {
            return $item;
        }

        $decoded          = json_decode((string) ($item->payload ?? ''), true);
        $item->normalised = \is_array($decoded) ? $decoded : [];
        $item->advisory   = (int) ($item->advisory_id ?? 0) > 0 ? (new AdvisoryRepository($this->getDatabase()))->find((int) $item->advisory_id) : null;

        if ($item->advisory === null && !empty($item->external_id)) {
            $item->advisory = (new AdvisoryRepository($this->getDatabase()))->findByCve((string) $item->external_id);
        }

        return $item;
    }

    /**
     * @param object $record The record.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    protected function canDelete($record): bool
    {
        return \Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('vel.feeds', 'com_vel');
    }
}
