<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Event;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Event\AbstractImmutableEvent;

/**
 * Fired by {@see \Jed\Component\Vel\Administrator\Service\VelService} after an advisory changes
 * state or status. The action log plugin, the notification sender and the JED bridge subscribe;
 * the service itself does not know they exist.
 *
 * Event names: onVelAdvisoryPublished, onVelAdvisoryRetired, onVelAdvisoryUpdated,
 * onVelAdvisoryStateChanged, onVelAdvisoryStatusChanged, onVelAdvisoryCveChanged.
 *
 * @since 4.0.0
 */
final class AdvisoryEvent extends AbstractImmutableEvent
{
    /**
     * @param string $name      The event name.
     * @param object $advisory  The advisory row as it stands after the change.
     * @param int    $actorId   Who did it (0 for automation).
     * @param array  $context   Anything else worth knowing (previous state, reason, ...).
     *
     * @since 4.0.0
     */
    public function __construct(string $name, object $advisory, int $actorId, array $context = [])
    {
        parent::__construct($name, ['advisory' => $advisory, 'actorId' => $actorId, 'context' => $context]);
    }

    /**
     * @return object
     *
     * @since 4.0.0
     */
    public function getAdvisory(): object
    {
        return $this->getArgument('advisory');
    }

    /**
     * @return int
     *
     * @since 4.0.0
     */
    public function getActorId(): int
    {
        return (int) $this->getArgument('actorId');
    }

    /**
     * @return array
     *
     * @since 4.0.0
     */
    public function getContext(): array
    {
        return (array) $this->getArgument('context');
    }
}
