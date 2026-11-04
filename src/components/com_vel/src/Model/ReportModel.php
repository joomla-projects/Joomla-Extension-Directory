<?php

/**
 * @package JED
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

use Jed\Component\Vel\Administrator\Enum\ReportKind;
use Jed\Component\Vel\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\Model\FormModel;
use RuntimeException;

/**
 * The public report form (VEL2-PRD §6.4.1). The rules live in {@see ReportService}; this model
 * loads the form and hands the validated data over.
 *
 * @since 4.0.0
 */
class ReportModel extends FormModel
{
    /**
     * @var string  The form name; FixnoticeModel overrides it.
     *
     * @since 4.0.0
     */
    protected string $formName = 'report';

    /**
     * @var ReportKind
     *
     * @since 4.0.0
     */
    protected ReportKind $kind = ReportKind::VULNERABILITY;

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
        $form = $this->loadForm('com_vel.' . $this->formName, $this->formName, ['control' => 'jform', 'load_data' => $loadData]);

        if (!$form) {
            return false;
        }

        $user = Factory::getApplication()->getIdentity();

        // A logged-in user's name and address come from the account, not the form.
        if ((int) $user->id > 0) {
            $form->setFieldAttribute('reporter_name', 'required', 'false');
            $form->setFieldAttribute('reporter_email', 'required', 'false');
            $form->setFieldAttribute('reporter_name', 'readonly', 'true');
            $form->setFieldAttribute('reporter_email', 'readonly', 'true');
            $form->setValue('reporter_name', null, $user->name);
            $form->setValue('reporter_email', null, $user->email);
        }

        // The captcha follows the component option, and is dropped when there is none.
        $captcha = (string) Factory::getApplication()->getParams('com_vel')->get('captcha', Factory::getApplication()->get('captcha', '0'));

        if ($captcha === '0' || $captcha === '') {
            $form->removeField('captcha');
        } else {
            $form->setFieldAttribute('captcha', 'plugin', $captcha);
        }

        return $form;
    }

    /**
     * @return array
     *
     * @since 4.0.0
     */
    protected function loadFormData()
    {
        $app  = Factory::getApplication();
        $data = (array) $app->getUserState('com_vel.' . $this->formName . '.data', []);

        if ($data === []) {
            $input = $app->getInput();

            // Arriving from a listing or an advisory pre-fills what is known.
            $data['extension_id']       = $input->getInt('extension_id', 0) ?: null;
            $data['extension_name']     = $input->getString('extension_name', '');
            $data['advisory_reference'] = $input->getInt('advisory', 0) > 0 ? \sprintf('VEL-%04d', $input->getInt('advisory', 0)) : '';
        }

        return $data;
    }

    /**
     * @param array $data  Validated form data.
     * @param array $files Uploaded files, $_FILES-style, one per entry.
     *
     * @return object  The report row with `reference` and `verification_sent`.
     *
     * @throws RuntimeException  Refused.
     *
     * @since 4.0.0
     */
    public function submit(array $data, array $files = []): object
    {
        $app = Factory::getApplication();

        // The honeypot: a hidden field a person never sees and a bot always fills.
        if (trim((string) ($data['website'] ?? '')) !== '') {
            throw new RuntimeException(\Joomla\CMS\Language\Text::_('COM_VEL_ERROR_SPAM_DETECTED'));
        }

        unset($data['website'], $data['captcha']);

        $service = new ReportService($this->getDatabase());
        $ipHash  = ReportService::ipHash((string) $app->getInput()->server->getString('REMOTE_ADDR', ''));

        return $service->submit($data, $this->kind, $app->getIdentity(), $ipHash, 'web', $files);
    }
}
