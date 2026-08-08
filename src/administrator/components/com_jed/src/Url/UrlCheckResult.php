<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Url;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * What a validator concluded about one URL.
 *
 * Three states, and only one of them stops anything:
 *
 * | State     | Comes from                       | Effect                        |
 * | --------- | -------------------------------- | ----------------------------- |
 * | `error`   | the format rules (layer 1 and 3) | blocks the save               |
 * | `notice`  | a validator (layer 2)            | shown, and overridable        |
 * | `ok`      | a validator that was satisfied   | confirmation, with a time     |
 *
 * That `notice` never blocks is a decision, not an oversight (13.4 point 5, against the design
 * draft's mandatory gate). Legitimate URLs fail a server-side check all the time: bot protection,
 * geoblocking, a 403 for an unusual user agent, a blanket block on datacentre address space. A
 * hard failure would lock developers out of their own form over somebody else's WAF rule.
 *
 * @since 4.1.0
 */
final class UrlCheckResult
{
    public const STATE_OK     = 'ok';
    public const STATE_NOTICE = 'notice';
    public const STATE_ERROR  = 'error';

    /**
     * @param string               $state   One of the STATE_* constants.
     * @param string               $message A language key describing the outcome.
     * @param array<string, mixed> $params  Substitutions for that language key.
     * @param int                  $status  The HTTP status observed, 0 when there was none.
     * @param string|null          $detail  A short free-text detail, e.g. the version found.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly string $state,
        public readonly string $message,
        public readonly array $params = [],
        public readonly int $status = 0,
        public readonly ?string $detail = null
    ) {
    }

    /**
     * @param string               $message Language key.
     * @param array<string, mixed> $params  Substitutions.
     * @param int                  $status  HTTP status.
     * @param string|null          $detail  Free-text detail.
     *
     * @return self
     *
     * @since 4.1.0
     */
    public static function ok(string $message, array $params = [], int $status = 0, ?string $detail = null): self
    {
        return new self(self::STATE_OK, $message, $params, $status, $detail);
    }

    /**
     * @param string               $message Language key.
     * @param array<string, mixed> $params  Substitutions.
     * @param int                  $status  HTTP status.
     *
     * @return self
     *
     * @since 4.1.0
     */
    public static function notice(string $message, array $params = [], int $status = 0): self
    {
        return new self(self::STATE_NOTICE, $message, $params, $status);
    }

    /**
     * @param string               $message Language key.
     * @param array<string, mixed> $params  Substitutions.
     *
     * @return self
     *
     * @since 4.1.0
     */
    public static function error(string $message, array $params = []): self
    {
        return new self(self::STATE_ERROR, $message, $params);
    }
}
