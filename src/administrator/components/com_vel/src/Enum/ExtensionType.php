<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Enum;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The Joomla extension types an affected product can be (brief §5.3).
 *
 * @since 4.0.0
 */
enum ExtensionType: string
{
    case COMPONENT = 'component';
    case MODULE    = 'module';
    case PLUGIN    = 'plugin';
    case TEMPLATE  = 'template';
    case LIBRARY   = 'library';
    case PACKAGE   = 'package';
    case FILE      = 'file';
    case LANGUAGE  = 'language';

    /**
     * Whether this type carries a plugin group in `folder`.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function hasFolder(): bool
    {
        return $this === self::PLUGIN;
    }

    /**
     * Whether this type is installed per client (site/administrator).
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function hasClient(): bool
    {
        return \in_array($this, [self::MODULE, self::TEMPLATE, self::LANGUAGE], true);
    }

    /**
     * @return string  Language key.
     *
     * @since 4.0.0
     */
    public function label(): string
    {
        return 'COM_VEL_EXT_TYPE_' . strtoupper($this->value);
    }
}
