<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Administrator\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\Table\Table;
use Joomla\Database\ParameterType;

/**
 * The single-case editor.
 *
 * The workflow itself is not here. Contacting the owner, marking a case abandoned and resolving it
 * all run through {@see CaseService} from the controller, because each carries a rule that has to
 * hold no matter who calls it. What this model does is the ordinary editing around them: notes, the
 * assignee, and the subject tuple of a case about something the JED never listed.
 *
 * @since 4.1.0
 */
class CaseModel extends AdminModel
{
    /**
     * @var string
     *
     * @since 4.1.0
     */
    protected $text_prefix = 'COM_ABANDONWARE';

    /**
     * @param string $type   The table type.
     * @param string $prefix The class prefix.
     * @param array  $config Configuration array.
     *
     * @return Table
     *
     * @since 4.1.0
     */
    public function getTable($type = 'Case', $prefix = 'Administrator', $config = []): Table
    {
        return $this->getMVCFactory()->createTable($type, $prefix, $config);
    }

    /**
     * @param array $data     Data for the form.
     * @param bool  $loadData Whether to load the data.
     *
     * @return Form|false
     *
     * @since 4.1.0
     */
    public function getForm($data = [], $loadData = true)
    {
        return $this->loadForm('com_abandonware.case', 'case', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /**
     * @return array|object
     *
     * @since 4.1.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_abandonware.edit.case.data', []);

        if (empty($data)) {
            // `?: []` and not the bare result: AdminModel::getItem() returns **false** for an id
            // that does not exist, and Form::bind(false) is a TypeError rather than an empty form.
            // Reaching a case id that is not there is an ordinary thing to do - a stale bookmark,
            // or a link from a ticket whose case was deleted - and it should be a 404, which is
            // what the view raises once this has stopped throwing first.
            $data = $this->getItem() ?: [];
        }

        return $data;
    }

    /**
     * Load a case, with the things the editor has to show alongside it.
     *
     * @param int|null $pk The case id.
     *
     * @return object|false
     *
     * @since 4.1.0
     */
    public function getItem($pk = null)
    {
        $item = parent::getItem($pk);

        if (!$item || empty($item->id)) {
            return $item;
        }

        $db      = $this->getDatabase();
        $caseId  = (int) $item->id;
        $signals = json_decode((string) ($item->signals ?? ''), true);

        $item->decoded_signals = \is_array($signals) ? $signals : [];

        // The reports behind the case. Reporter identity is loaded because this is the backend and
        // an abuse assessment needs to know who filed what - it never reaches a public view.
        $item->reports = $db->setQuery(
            $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__jed_abandonware_reports'))
                ->where($db->quoteName('case_id') . ' = :cid')
                ->bind(':cid', $caseId, ParameterType::INTEGER)
                ->order($db->quoteName('created') . ' ASC')
        )->loadObjectList() ?: [];

        // The live link state for the listing, so whoever works the case can see the evidence
        // rather than only the sentence the signal wrote (P1-09).
        $item->linkchecks = [];

        if ((int) ($item->extension_id ?? 0) > 0) {
            $extensionId      = (int) $item->extension_id;
            $item->linkchecks = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['link_type', 'url', 'status', 'http_code', 'message', 'fail_count', 'first_failed', 'escalated']))
                    ->from($db->quoteName('#__jed_extension_linkchecks'))
                    ->where($db->quoteName('extension_id') . ' = :eid')
                    ->where($db->quoteName('status') . ' <> ' . $db->quote('ok'))
                    ->bind(':eid', $extensionId, ParameterType::INTEGER)
                    ->order($db->quoteName('fail_count') . ' DESC')
            )->loadObjectList() ?: [];

            $item->listing = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'owner', 'state', 'approved', 'blocked', 'deleted', 'modified', 'extension_version', 'last_update_check', 'last_update_check_error']))
                    ->from($db->quoteName('#__jed_extensions'))
                    ->where($db->quoteName('id') . ' = :eid2')
                    ->bind(':eid2', $extensionId, ParameterType::INTEGER)
            )->loadObject();
        }

        return $item;
    }

    /**
     * Save the editable half of a case.
     *
     * `status`, `contact_time`, `abandoned_time`, `resolution` and `published` are stripped rather
     * than validated. They are the columns the process's guarantees are written on, and the whole
     * reason {@see CaseService} exists is that they must only ever move through it - a form post
     * that could set `status` to `abandoned` would walk straight past the contact-attempt gate,
     * which is one of this plan's acceptance criteria.
     *
     * @param array $data The submitted data.
     *
     * @return bool
     *
     * @since 4.1.0
     */
    public function save($data): bool
    {
        foreach (['status', 'contact_time', 'contact_by', 'grace_until', 'abandoned_time', 'abandoned_by', 'resolved_time', 'resolved_by', 'resolution', 'published', 'signals', 'ticket_id'] as $owned) {
            unset($data[$owned]);
        }

        return parent::save($data);
    }
}
