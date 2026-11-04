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

use Jed\Component\Vel\Administrator\Service\AttachmentService;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

/**
 * One report, read-only apart from the decisions ReportService records.
 *
 * @since 4.0.0
 */
class ReportModel extends AdminModel
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
    public function getTable($type = 'Report', $prefix = 'Administrator', $config = []): Table
    {
        return $this->getMVCFactory()->createTable($type, $prefix, $config);
    }

    /**
     * No editable form: a report is what somebody submitted, and editing it would make it
     * something else. The decisions are toolbar actions.
     *
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
     * @param int|null $pk The report id.
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

        $db = $this->getDatabase();

        $item->attachments = (new AttachmentService($db))->forThread([], [(int) $item->id]);

        $item->advisory = null;

        if ((int) ($item->advisory_id ?? 0) > 0) {
            $item->advisory = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'title', 'state', 'status', 'cve_id']))
                    ->from($db->quoteName('#__vel_advisories'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $item->advisory_id, ParameterType::INTEGER)
            )->loadObject();
        }

        // Candidate advisories to attach to: the ones naming the same extension or vendor.
        $item->candidates = [];

        if ((int) ($item->advisory_id ?? 0) === 0) {
            $like  = '%' . trim((string) $item->extension_name) . '%';
            $query = $db->getQuery(true)
                ->select($db->quoteName(['a.id', 'a.title', 'a.state', 'a.status']))
                ->from($db->quoteName('#__vel_advisories', 'a'))
                ->innerJoin($db->quoteName('#__vel_advisory_products', 'p'), $db->quoteName('p.advisory_id') . ' = ' . $db->quoteName('a.id'))
                ->order($db->quoteName('a.modified') . ' DESC')
                ->group($db->quoteName(['a.id', 'a.title', 'a.state', 'a.status']));

            $or = [];

            if (trim((string) $item->extension_name) !== '') {
                $or[] = $db->quoteName('p.product') . ' LIKE :name';
                $query->bind(':name', $like);
            }

            if ((int) ($item->extension_id ?? 0) > 0) {
                $or[] = $db->quoteName('p.extension_id') . ' = :eid';
                $query->bind(':eid', $item->extension_id, ParameterType::INTEGER);
            }

            if ($or !== []) {
                $query->where('(' . implode(' OR ', $or) . ')');
                $item->candidates = $db->setQuery($query, 0, 10)->loadObjectList() ?: [];
            }
        }

        $item->reference = \sprintf('VELR-%05d', (int) $item->id);

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
        return \Joomla\CMS\Factory::getApplication()->getIdentity()->authorise('core.delete', 'com_vel');
    }
}
