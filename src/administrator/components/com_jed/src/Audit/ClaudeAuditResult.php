<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Component\Jed\Administrator\Audit;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The result of a {@see ClaudeAuditor::audit()} call.
 *
 * @since 4.1.0
 */
final class ClaudeAuditResult
{
    /**
     * @param string $reportMarkdown The structured Markdown security report (or an explanatory
     *                               message when $available is false).
     * @param bool   $available      False if the audit could not be completed (refusal, API error,
     *                               missing API key, truncation) — the pipeline degrades gracefully
     *                               rather than failing the whole job.
     *
     * @since 4.1.0
     */
    public function __construct(
        public readonly string $reportMarkdown,
        public readonly bool $available = true
    ) {
    }
}
