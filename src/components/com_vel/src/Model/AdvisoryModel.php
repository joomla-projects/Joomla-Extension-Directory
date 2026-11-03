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

use Jed\Component\Vel\Administrator\Service\AdvisoryRepository;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Database\ParameterType;

/**
 * One public advisory (brief §4.2).
 *
 * Reads through {@see AdvisoryRepository} with `publicOnly` on, so a draft id in the URL is a
 * 404 and not a leak - by HTML, by JSON, by anything that uses this model.
 *
 * @since 4.0.0
 */
class AdvisoryModel extends BaseDatabaseModel
{
    /**
     * @var object|null
     *
     * @since 4.0.0
     */
    private ?object $item = null;

    /**
     * @param int|null $id The advisory id, or null for the request's.
     *
     * @return object|null  The advisory with children, or null when there is nothing public.
     *
     * @since 4.0.0
     */
    public function getItem(?int $id = null): ?object
    {
        if ($this->item !== null) {
            return $this->item;
        }

        $id ??= (int) Factory::getApplication()->getInput()->getInt('id', 0);

        $repository = new AdvisoryRepository($this->getDatabase());
        $this->item = $repository->find($id, true);

        return $this->item;
    }

    /**
     * Count a view. One statement, no row read back; the number is for the team's sense of what
     * is being looked at, nothing more.
     *
     * @param int $id The advisory.
     *
     * @return void
     *
     * @since 4.0.0
     */
    public function hit(int $id): void
    {
        if ($id <= 0) {
            return;
        }

        try {
            $db = $this->getDatabase();
            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__vel_advisories'))
                    ->set($db->quoteName('hits') . ' = ' . $db->quoteName('hits') . ' + 1')
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $id, ParameterType::INTEGER)
            )->execute();
        } catch (\Throwable $e) {
            // Not worth a message.
        }
    }
}
