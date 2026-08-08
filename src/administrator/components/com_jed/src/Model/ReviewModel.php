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
use Jed\Component\Jed\Administrator\Log\JedActionLog;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\CMS\User\UserFactoryInterface;
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
        $db     = $this->getDatabase();
        $ids    = array_map('intval', (array) $pks);
        $before = [];

        if ($ids !== []) {
            $query = $db->getQuery(true)
                ->select($db->quoteName(['r.id', 'r.extension_id', 'r.state', 'r.created_by', 'r.title']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->from($db->quoteName('#__jed_reviews', 'r'))
                ->leftJoin($db->quoteName('#__jed_extensions', 'e') . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.extension_id'))
                ->whereIn($db->quoteName('r.id'), $ids);
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

                $wasPublished = (int) $old->state === 1;

                if ($wasPublished !== $nowPublished) {
                    $extensionIds[(int) $old->extension_id] = true;

                    // 4.3: moderating a review told nobody. The reviewer wrote it and never heard
                    // whether it was published, which is the one thing they wanted to know.
                    $this->notifyReviewer($old, $nowPublished);

                    // On the transition only - the same test the mail uses. Re-publishing what is
                    // already public is not a decision and must not fill the log with repeats.
                    $this->logReviewDecision(
                        $nowPublished ? JedActionLog::REVIEW_PUBLISH : JedActionLog::REVIEW_UNPUBLISH,
                        $old
                    );
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
     * Tell the reviewer what happened to their review.
     *
     * Sent on the transition only, not on every save, so re-publishing something already public
     * does not mail anyone. A failure here is logged rather than raised: the moderation decision
     * is already committed, and a mail server being down must not undo it.
     *
     * @param object $review    The review row as it was before the change.
     * @param bool   $published Whether it is now published.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function notifyReviewer(object $review, bool $published): void
    {
        $userId = (int) ($review->created_by ?? 0);

        if ($userId <= 0) {
            return;
        }

        try {
            $reviewer = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);

            if (empty($reviewer->email)) {
                return;
            }

            $mailer = new MailTemplate(
                $published ? 'com_jed.review_approved' : 'com_jed.review_rejected',
                Factory::getApplication()->getLanguage()->getTag()
            );
            $mailer->addTemplateData([
                'EXTENSIONNAME' => (string) ($review->extension_name ?? ''),
                'REASONNOTES'   => '',
                'SITENAME'      => (string) Factory::getApplication()->get('sitename'),
            ]);
            $mailer->addRecipient($reviewer->email, $reviewer->name);
            $mailer->send();
        } catch (\Throwable $e) {
            Log::add(
                sprintf('com_jed: could not notify the author of review %d: %s', (int) $review->id, $e->getMessage()),
                Log::WARNING,
                'com_jed'
            );
        }
    }

    /**
     * Approves one or more pending developer responses (developer_response_published = 1) - the
     * ticket-based counterpart to a new review's `reviews.publish` Approve action.
     *
     * @param array $pks The review ids whose developer_response should be published.
     *
     * @return bool True on success.
     *
     * @since 4.1.0
     */
    public function publishResponse(array $pks): bool
    {
        return $this->setDeveloperResponsePublished($pks, 1);
    }

    /**
     * Rejects/deletes one or more developer responses (developer_response_published = -2) -
     * the response text itself is left in place, just hidden from the extension page.
     *
     * @param array $pks The review ids whose developer_response should be rejected.
     *
     * @return bool True on success.
     *
     * @since 4.1.0
     */
    public function deleteResponse(array $pks): bool
    {
        return $this->setDeveloperResponsePublished($pks, -2);
    }

    /**
     * @param array $pks   The review ids to update.
     * @param int   $value The developer_response_published value to set.
     *
     * @return bool True on success.
     *
     * @since 4.1.0
     */
    private function setDeveloperResponsePublished(array $pks, int $value): bool
    {
        $ids = array_filter(array_map('intval', $pks));

        if (empty($ids)) {
            return false;
        }

        $db = $this->getDatabase();

        $before = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName(['r.id', 'r.title', 'r.developer_response_published']))
                ->select($db->quoteName('e.name', 'extension_name'))
                ->from($db->quoteName('#__jed_reviews', 'r'))
                ->leftJoin(
                    $db->quoteName('#__jed_extensions', 'e')
                    . ' ON ' . $db->quoteName('e.id') . ' = ' . $db->quoteName('r.extension_id')
                )
                ->whereIn($db->quoteName('r.id'), $ids)
        )->loadObjectList('id');

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__jed_reviews'))
            ->set($db->quoteName('developer_response_published') . ' = ' . (int) $value)
            ->whereIn($db->quoteName('id'), $ids);

        $db->setQuery($query)->execute();

        foreach ($ids as $id) {
            $old = $before[$id] ?? null;

            // Transitions only, as for the review itself: approving a response that is already
            // approved decides nothing.
            if ($old === null || (int) $old->developer_response_published === $value) {
                continue;
            }

            $this->logReviewDecision(
                $value === 1 ? JedActionLog::RESPONSE_PUBLISH : JedActionLog::RESPONSE_UNPUBLISH,
                $old
            );
        }

        return true;
    }

    /**
     * Record one review-moderation decision in the action log (`P1-22`).
     *
     * @param string $action A {@see JedActionLog} review or response action.
     * @param object $review A row carrying id, title and extension_name.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function logReviewDecision(string $action, object $review): void
    {
        JedActionLog::loadWording();

        JedActionLog::record($action, 'com_jed.review', (int) $review->id, [
            // A review with no title is normal in the imported stock; falling back to the
            // extension keeps the entry a sentence rather than a dangling "the review of".
            'title'     => trim((string) ($review->title ?? '')) ?: Text::_('COM_JED_REVIEW_UNTITLED'),
            'extension' => (string) ($review->extension_name ?? ''),
        ]);
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
