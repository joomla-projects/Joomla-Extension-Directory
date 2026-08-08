<?php

/**
 * @package JED
 *
 * @subpackage Abandonware
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Abandonware\Site\View\Report;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Jed\Component\Abandonware\Administrator\Service\ReportService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Throwable;

/**
 * The report form.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $form       = null;
    public ?Registry $params = null;

    /**
     * Whether the visitor may submit at all, and why not if not.
     *
     * Asked here and not only on submit. Drawing a form somebody is not allowed to send and
     * refusing it after they have written three paragraphs is the wrong order - the refusal is the
     * same either way, and the difference is whether it wastes their time.
     *
     * @var bool
     *
     * @since 4.0.0
     */
    public bool $mayReport = false;

    /**
     * @var string
     *
     * @since 4.0.0
     */
    public string $refusal = '';

    /**
     * @param string|null $tpl The template name.
     *
     * @return void
     *
     * @throws Exception
     *
     * @since 4.0.0
     */
    public function display($tpl = null): void
    {
        $this->form   = $this->getModel()->getForm();
        $this->params = Factory::getApplication()->getParams();

        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            (new ReportService($db, new CaseService($db)))
                ->assertMayReport(Factory::getApplication()->getIdentity());

            $this->mayReport = true;
        } catch (Throwable $e) {
            $this->mayReport = false;
            $this->refusal   = $e->getMessage();
        }

        parent::display($tpl);
    }
}
