<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Parser;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Abstract base class for Joomla extension manifest parsers.
 *
 * @since 4.0.0
 */
abstract class Parser
{
    /**
     * Returns the author/owner of the extension as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getOwner(): string;

    /**
     * Returns the name of the extension as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getName(): string;

    /**
     * Returns the changelog URL as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getChangelogUrl(): string;

    /**
     * Returns the update server URL from the first <updateservers> entry.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getUpdateServerUrl(): string;

    /**
     * Returns the version string as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getVersion(): string;

    /**
     * Returns the author's website URL as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getAuthorUrl(): string;

    /**
     * Returns the author's email address as declared in the manifest.
     *
     * @return string
     * @since  4.0.0
     */
    abstract public function getAuthorEmail(): string;

    /**
     * Returns the distinct Joomla extension type(s) found in the manifest (e.g. "component",
     * "module", "plugin"). For a package manifest this collects the type of every bundled
     * <files><file> entry; for a single-extension manifest it falls back to the root
     * <extension type="..."> attribute.
     *
     * @return string[]
     * @since  4.0.0
     */
    abstract public function getExtensionTypes(): array;
}
