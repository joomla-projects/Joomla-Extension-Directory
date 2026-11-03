<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Vel\Administrator\Version;

// No direct access.
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\ExtensionType;

/**
 * The Joomla tuple that names an installed extension (brief §5.3): type, element, folder,
 * client_id. Normalised once here so every comparison in the component agrees on what "the
 * same extension" means.
 *
 * @since 4.0.0
 */
final class Identity
{
    /**
     * @param ExtensionType|null $type     The type, or null when unknown.
     * @param string|null        $element  Lower-cased element, or null.
     * @param string|null        $folder   Lower-cased plugin group, or null.
     * @param int|null           $clientId 0 site, 1 administrator, null either/unknown.
     *
     * @since 4.0.0
     */
    public function __construct(
        public readonly ?ExtensionType $type,
        public readonly ?string $element,
        public readonly ?string $folder,
        public readonly ?int $clientId
    ) {
    }

    /**
     * @param mixed $type     Anything that might be a type.
     * @param mixed $element  Anything that might be an element.
     * @param mixed $folder   Anything that might be a folder.
     * @param mixed $clientId Anything that might be a client id ('', null, 0, 1, '0', 'site'...).
     *
     * @return self
     *
     * @since 4.0.0
     */
    public static function normalise(mixed $type, mixed $element, mixed $folder, mixed $clientId): self
    {
        $type = \is_string($type) ? ExtensionType::tryFrom(strtolower(trim($type))) : null;

        $element = \is_string($element) ? strtolower(trim($element)) : null;
        $element = $element !== null && preg_match('/^[a-z0-9_\-.]+$/', $element) ? $element : null;

        $folder = \is_string($folder) ? strtolower(trim($folder)) : null;
        $folder = $folder !== null && preg_match('/^[a-z0-9_\-]+$/', $folder) ? $folder : null;

        // Only plugins have a group; a folder on anything else is noise.
        if ($type !== null && !$type->hasFolder()) {
            $folder = null;
        }

        if (\is_string($clientId)) {
            $clientId = match (strtolower(trim($clientId))) {
                '0', 'site'                   => 0,
                '1', 'administrator', 'admin' => 1,
                default                       => null,
            };
        } elseif (\is_int($clientId) || \is_float($clientId) || \is_bool($clientId)) {
            $clientId = (int) $clientId === 1 ? 1 : 0;
        } else {
            $clientId = null;
        }

        return new self($type, $element, $folder, $clientId);
    }

    /**
     * @return bool  Whether there is enough here to match anything at all.
     *
     * @since 4.0.0
     */
    public function isKnown(): bool
    {
        return $this->type !== null && $this->element !== null;
    }

    /**
     * Whether an installed extension ($this) is the one an advisory product ($advisory) names.
     *
     * Type and element must match. A plugin's folder must match. The client must match unless
     * the advisory left it NULL - "either" - which is the common case for modules that ship for
     * both sides.
     *
     * @param Identity $advisory The identity stored on the advisory product.
     *
     * @return bool
     *
     * @since 4.0.0
     */
    public function matches(Identity $advisory): bool
    {
        if (!$this->isKnown() || !$advisory->isKnown()) {
            return false;
        }

        if ($this->type !== $advisory->type || $this->element !== $advisory->element) {
            return false;
        }

        if ($advisory->type->hasFolder() && ($advisory->folder ?? '') !== ($this->folder ?? '')) {
            return false;
        }

        if ($advisory->clientId !== null && $this->clientId !== null && $advisory->clientId !== $this->clientId) {
            return false;
        }

        return true;
    }

    /**
     * @return string  e.g. "plugin/system/modals", "component/com_foo", "module/mod_foo@1".
     *
     * @since 4.0.0
     */
    public function __toString(): string
    {
        if (!$this->isKnown()) {
            return '';
        }

        $key = $this->type->value . '/' . ($this->folder !== null ? $this->folder . '/' : '') . $this->element;

        if ($this->clientId !== null && $this->type->hasClient()) {
            $key .= '@' . $this->clientId;
        }

        return $key;
    }
}
