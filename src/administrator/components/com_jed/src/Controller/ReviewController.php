<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Jed\Component\Jed\Administrator\Model\ReviewModel;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;

/**
 * Review controller class.
 *
 * @since 4.0.0
 */
class ReviewController extends FormController
{
    protected $view_list = 'reviews';


    /**
     * setPublished
     *
     * function for ajax setting a review's published status
     *
     * Goes through {@see \Jed\Component\Jed\Administrator\Table\ReviewTable::store()}
     * (rather than a raw UPDATE) so the automatic score-recalculation hook there fires
     * when this changes the review's published-ness.
     *
     * @since  4.0.0
     * @throws Exception
     */
    public function setPublished()
    {
        $this->checkToken();

        $app      = Factory::getApplication();
        $reviewId = $app->getInput()->getInt('itemId', 0);
        $optionId = $app->getInput()->getInt('optionId', 0);

        if (!$reviewId) {
            return;
        }

        /** @var ReviewModel $model */
        $model = $this->getModel();
        $table = $model->getTable();

        if ($table->load($reviewId)) {
            $table->published = $optionId;
            $table->store();
        }
    }
}
