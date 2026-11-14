<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Cvss;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use InvalidArgumentException;

/**
 * A CVSS vector the parser will not accept. The message is a language key and the arguments
 * travel with it: the CVSS classes run without Joomla (they are unit-tested that way), so the
 * translation happens where the exception is caught, with `Text::sprintf($e->getMessage(),
 * ...$e->args)`.
 *
 * @since 4.0.0
 */
final class CvssException extends InvalidArgumentException
{
    /**
     * @param string $key  The language key.
     * @param array  $args Arguments for `Text::sprintf`.
     *
     * @since 4.0.0
     */
    public function __construct(string $key, public readonly array $args = [])
    {
        parent::__construct($key);
    }
}
