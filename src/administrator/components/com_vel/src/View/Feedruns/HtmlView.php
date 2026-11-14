<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\View\Feedruns;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Vel\Administrator\Feed\FeedRunner;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\WebAsset\Exception\UnknownAssetException;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * The feed run log.
 *
 * @since 4.0.0
 */
class HtmlView extends BaseHtmlView
{
    public ?Form $filterForm    = null;
    public array $activeFilters = [];
    protected array $items      = [];
    protected Pagination $pagination;
    protected Registry $state;

    /**
     * @var array<string, bool>
     *
     * @since 4.0.0
     */
    public array $can = [];

    /**
     * The run named by `&run=` in the URL (where the run buttons send the browser), as
     * {@see FeedRunner::report()} describes it; null for the plain list.
     *
     * @var array|null
     *
     * @since 4.0.0
     */
    public ?array $progress = null;

    /**
     * Why the progress panel cannot work on this server, when it cannot.
     *
     * @var string
     *
     * @since 4.0.0
     */
    public string $progressProblem = '';

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
        $model = $this->getModel();

        $this->items         = (array) $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        $user      = Factory::getApplication()->getIdentity();
        $this->can = [
            'feeds' => (bool) $user->authorise('vel.feeds', 'com_vel'),
            'admin' => (bool) $user->authorise('core.admin', 'com_vel'),
        ];

        $runId = Factory::getApplication()->getInput()->getInt('run', 0);

        if ($runId > 0 && $this->can['feeds']) {
            $this->prepareProgress($runId);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * The progress panel of one run: its state now, and the script that steps it while the page
     * is open (media/com_vel/js/feedrun.js).
     *
     * @param int $runId The run.
     *
     * @return void
     *
     * @since 4.0.0
     */
    protected function prepareProgress(int $runId): void
    {
        $db     = Factory::getContainer()->get(DatabaseInterface::class);
        $runner = new FeedRunner($db, new VelService($db));
        $run    = $runner->load($runId);

        if ($run === null) {
            return;
        }

        $this->progress = $runner->report($run);

        foreach (
            [
                'COM_VEL_FEEDRUN_STOPPING', 'COM_VEL_FEEDRUN_CONFIRM_STOP', 'COM_VEL_FEEDRUN_BUSY', 'COM_VEL_FEEDRUN_RETRY',
                'COM_VEL_FEEDRUN_GAVE_UP', 'COM_VEL_FEEDRUN_BAD_RESPONSE', 'COM_VEL_FEEDRUN_LEAVE_WARNING',
                'COM_VEL_FEEDRUN_EVENT_NEW', 'COM_VEL_FEEDRUN_EVENT_UPDATED', 'COM_VEL_FEEDRUN_SCRIPT_FAILED',
                'COM_VEL_FEEDRUN_REQUESTS_DROPPED',
            ] as $key
        ) {
            Text::script($key);
        }

        // The step requests carry the token in a header (checked by Session::checkToken()). It
        // reaches the page as the `csrf.token` script option, which the core asset adds for us:
        // Text::script() above has already pulled `core` in.
        $document = $this->getDocument();

        // media/com_vel/joomla.asset.json is registered by the administrator application for
        // the component being dispatched.
        $wa = $document->getWebAssetManager();

        // The panel is drawn here but only the script moves it: a missing script file is a page
        // that silently never updates (Joomla drops an asset whose file it cannot find), so say so.
        try {
            $wa->useScript('com_vel.feedrun');

            $scriptMissing = $wa->getAsset('script', 'com_vel.feedrun')->getUri() === '';
        } catch (UnknownAssetException $e) {
            $scriptMissing = true;
        }

        $document->addScriptOptions('com_vel.feedrun', [
            'id'      => $runId,
            'report'  => $this->progress,
            'stepUrl' => Route::_('index.php?option=com_vel&task=feeditems.step&format=json', false),
            'stopUrl' => Route::_('index.php?option=com_vel&task=feeditems.stop&format=json', false),
            'listUrl' => Route::_('index.php?option=com_vel&view=feedruns', false),
        ]);
        if ($scriptMissing) {
            $this->progressProblem = Text::sprintf('COM_VEL_FEEDRUN_SCRIPT_MISSING', 'media/com_vel/js/feedrun.js', 'media/com_vel/joomla.asset.json');

            Factory::getApplication()->enqueueMessage($this->progressProblem, 'error');
        }
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_VEL_FEEDRUNS_TITLE'), 'shield');
      //  $bar->linkButton('tickets')->text(Text::_('COM_JED_TITLE_TICKETS'))->url('index.php?option=com_jed&view=tickets')->icon('fa fa-ticket-alt');

        if ($this->can['feeds']) {
            $toolbar->standardButton('run-cna', 'COM_VEL_TOOLBAR_RUN_CNA', 'feeditems.runCna')->icon('icon-sync')->listCheck(false);
            $toolbar->standardButton('run-nvd', 'COM_VEL_TOOLBAR_RUN_NVD', 'feeditems.runNvd')->icon('icon-sync')->listCheck(false);
            $toolbar->confirmButton('seed', 'COM_VEL_TOOLBAR_SEED', 'feeditems.seed')->message('COM_VEL_CONFIRM_SEED')->icon('icon-database')->listCheck(false);
        }

        if ($this->can['admin']) {
            $toolbar->preferences('com_vel');
        }
    }
}
