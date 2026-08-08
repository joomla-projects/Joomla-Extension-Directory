<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\Model;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Jed\Component\Abandonware\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\ParameterType;

/**
 * The public report form.
 *
 * A thin shell over {@see ReportService}. Everything that decides whether a submission is accepted
 * - the privilege, the ban, the rate limit, the consent, the duplicate - lives in that service, so
 * that a future API endpoint reaches the same answers as this form without either being the place
 * the rules are written down.
 *
 * @since 4.1.0
 */
class ReportModel extends FormModel
{
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
        return $this->loadForm('com_abandonware.report', 'report', ['control' => 'jform', 'load_data' => $loadData]) ?: false;
    }

    /**
     * @return array
     *
     * @since 4.1.0
     */
    protected function loadFormData(): array
    {
        $app  = Factory::getApplication();
        $data = (array) $app->getUserState('com_abandonware.report.data', []);

        // Pre-fill from the listing when the visitor arrived from a detail page. The name and
        // version are what the reporter would otherwise retype from the page they just left, and
        // getting them wrong is what makes a report unresolvable.
        $extensionId = (int) $app->getInput()->getInt('extension_id', (int) ($data['extension_id'] ?? 0));

        if ($extensionId > 0 && empty($data['extension_name'])) {
            $db      = $this->getDatabase();
            $listing = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['id', 'name', 'extension_version']))
                    ->from($db->quoteName('#__jed_extensions'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->where($db->quoteName('deleted') . ' = 0')
                    ->bind(':id', $extensionId, ParameterType::INTEGER)
            )->loadAssoc();

            if ($listing !== null) {
                $data['extension_id']      = (int) $listing['id'];
                $data['extension_name']    = (string) $listing['name'];
                $data['extension_version'] = (string) $listing['extension_version'];
            }
        }

        return $data;
    }

    /**
     * File the report.
     *
     * @param array $data The submitted form data.
     *
     * @return object  The service's result: report id, case id, case.
     *
     * @since 4.1.0
     */
    public function submit(array $data): object
    {
        $db = $this->getDatabase();

        return (new ReportService($db, new CaseService($db)))
            ->submit($data, Factory::getApplication()->getIdentity());
    }
}
