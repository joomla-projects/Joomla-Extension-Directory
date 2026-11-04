<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Table;

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

/**
 * The `#__vel_advisory_products` row.
 *
 * @since 4.0.0
 */
class AdvisoryProductTable extends Table
{
    /**
     * @param DatabaseDriver $db A database connector object.
     *
     * @since 4.0.0
     */
    public function __construct(DatabaseDriver $db)
    {
        $this->typeAlias = 'com_vel.product';

        parent::__construct('#__vel_advisory_products', 'id', $db);
    }
}
