<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Controller;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Enum\FeedDecision;
use Jed\Component\Vel\Administrator\Enum\FeedSource;
use Jed\Component\Vel\Administrator\Feed\AdvisoryMerger;
use Jed\Component\Vel\Administrator\Feed\FeedRecord;
use Jed\Component\Vel\Administrator\Service\VelService;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Throwable;

/**
 * One feed item and the three decisions about it: create, merge, ignore.
 *
 * @since 4.0.0
 */
class FeeditemController extends FormController
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $view_list = 'feeditems';

    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected $text_prefix = 'COM_VEL_FEEDITEM';

    /**
     * @param int $id The item.
     *
     * @return array{item: object, record: FeedRecord}|null
     *
     * @since 4.0.0
     */
    private function load(int $id): ?array
    {
        if (!$this->app->getIdentity()->authorise('vel.feeds', 'com_vel')) {
            $this->fail($id, Text::_('JERROR_ALERTNOAUTHOR'));

            return null;
        }

        $db   = Factory::getContainer()->get(DatabaseInterface::class);
        $item = $db->setQuery(
            $db->getQuery(true)->select('*')->from($db->quoteName('#__vel_feed_items'))
                ->where($db->quoteName('id') . ' = :id')->bind(':id', $id, ParameterType::INTEGER)
        )->loadObject();

        if ($item === null) {
            $this->fail(0, Text::_('COM_VEL_ERROR_FEED_ITEM_NOT_FOUND'));

            return null;
        }

        $normalised = json_decode((string) $item->payload, true);
        $record     = new FeedRecord(
            FeedSource::tryFrom((string) $item->source) ?? FeedSource::CNA,
            (string) $item->external_id,
            \is_array($normalised) ? $normalised : [],
            [],
            FeedClassification::tryFrom((string) $item->classification)
        );

        return ['item' => $item, 'record' => $record];
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function create(): void
    {
        $this->checkToken();

        $id     = (int) $this->input->getInt('id', 0);
        $loaded = $this->load($id);

        if ($loaded === null) {
            return;
        }

        if ($loaded['record']->classification === FeedClassification::JOOMLA_CORE) {
            $this->fail($id, Text::_('COM_VEL_ERROR_FEED_CORE'));

            return;
        }

        // A rejected CVE never becomes an advisory (feeds PRD §5.1 rule 2). The button is not
        // rendered for one, but the task is reachable by URL.
        if ($loaded['record']->classification === FeedClassification::REJECTED) {
            $this->fail($id, Text::_('COM_VEL_ERROR_FEED_REJECTED'));

            return;
        }

        try {
            $db         = Factory::getContainer()->get(DatabaseInterface::class);
            $merger     = new AdvisoryMerger($db, new VelService($db));
            $existing   = $merger->findExisting($loaded['record']);
            $advisoryId = $existing !== null ? (int) $existing->id : $merger->create($loaded['record'], (int) $this->app->getIdentity()->id);

            $this->decide($id, $existing !== null ? FeedDecision::MERGED : FeedDecision::CREATED, $advisoryId);

            if ($existing !== null) {
                $merger->merge($loaded['record'], $advisoryId, (int) $this->app->getIdentity()->id);
            }
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->releaseEditId($this->option . '.edit.' . $this->context, $id);

        $this->setRedirect(Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $advisoryId, false), Text::sprintf('COM_VEL_MSG_FEED_CREATED', \sprintf('VEL-%04d', $advisoryId)));
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function merge(): void
    {
        $this->checkToken();

        $id     = (int) $this->input->getInt('id', 0);
        $loaded = $this->load($id);

        if ($loaded === null) {
            return;
        }

        $advisoryId = (int) $this->input->getInt('advisory_id', (int) ($loaded['item']->advisory_id ?? 0));

        if ($advisoryId <= 0) {
            $reference  = trim((string) $this->input->getString('advisory_reference', ''));
            $advisoryId = $reference !== '' ? (new \Jed\Component\Vel\Administrator\Service\AdvisoryRepository(Factory::getContainer()->get(DatabaseInterface::class)))->resolveReference($reference) : 0;
        }

        if ($advisoryId <= 0) {
            $this->fail($id, Text::_('COM_VEL_ERROR_ATTACH_MISSING'));

            return;
        }

        try {
            $db    = Factory::getContainer()->get(DatabaseInterface::class);
            $added = (new AdvisoryMerger($db, new VelService($db)))->merge($loaded['record'], $advisoryId, (int) $this->app->getIdentity()->id);

            $this->decide($id, FeedDecision::MERGED, $advisoryId);
        } catch (Throwable $e) {
            $this->fail($id, $e->getMessage());

            return;
        }

        $this->releaseEditId($this->option . '.edit.' . $this->context, $id);

        $this->setRedirect(
            Route::_('index.php?option=com_vel&task=advisory.edit&id=' . $advisoryId, false),
            $added === [] ? Text::_('COM_VEL_MSG_FEED_NOTHING_TO_MERGE') : Text::sprintf('COM_VEL_MSG_FEED_MERGED', implode(', ', $added))
        );
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    public function ignore(): void
    {
        $this->checkToken();

        $id     = (int) $this->input->getInt('id', 0);
        $loaded = $this->load($id);

        if ($loaded === null) {
            return;
        }

        $this->decide($id, FeedDecision::IGNORED, (int) ($loaded['item']->advisory_id ?? 0) ?: null);
        $this->releaseEditId($this->option . '.edit.' . $this->context, $id);

        $this->setRedirect(Route::_('index.php?option=com_vel&view=feeditems', false), Text::_('COM_VEL_MSG_FEED_IGNORED'));
    }

    /**
     * @param int          $id         The item.
     * @param FeedDecision $decision   The decision.
     * @param int|null     $advisoryId The advisory, if any.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function decide(int $id, FeedDecision $decision, ?int $advisoryId): void
    {
        $db    = Factory::getContainer()->get(DatabaseInterface::class);
        $value = $decision->value;
        $me    = (int) $this->app->getIdentity()->id;
        $now   = Factory::getDate()->toSql();

        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__vel_feed_items'))
                ->set($db->quoteName('decision') . ' = :decision')
                ->set($db->quoteName('advisory_id') . ' = :aid')
                ->set($db->quoteName('decided_by') . ' = :me')
                ->set($db->quoteName('decided_time') . ' = :now')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':decision', $value)
                ->bind(':aid', $advisoryId, ParameterType::INTEGER)
                ->bind(':me', $me, ParameterType::INTEGER)
                ->bind(':now', $now)
                ->bind(':id', $id, ParameterType::INTEGER)
        )->execute();
    }

    /**
     * @param int    $id      The item.
     * @param string $message The refusal.
     *
     * @return void
     *
     * @since 4.0.0
     */
    private function fail(int $id, string $message): void
    {
        $this->setRedirect(
            Route::_($id > 0 ? 'index.php?option=com_vel&task=feeditem.edit&id=' . $id : 'index.php?option=com_vel&view=feeditems', false),
            $message,
            'error'
        );
    }
}
