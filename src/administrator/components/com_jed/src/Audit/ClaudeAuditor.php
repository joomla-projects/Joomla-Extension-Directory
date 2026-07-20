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

use Joomla\Http\Http;
use Throwable;

/**
 * Calls the Anthropic Messages API directly (host-side, not from inside the
 * network-isolated Docker container) to perform a security review of an
 * extension's source: SQL injection, privilege escalation / missing ACL checks,
 * unrestricted file uploads, and other findings.
 *
 * @since 4.1.0
 */
class ClaudeAuditor
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const MAX_SOURCE_FILES = 200;

    private const MAX_SOURCE_BYTES = 1_500_000;

    /**
     * @param Http   $http   The HTTP client used to call the Anthropic API.
     * @param string $apiKey The Anthropic API key (from com_jed's component configuration).
     * @param string $model  The Claude model to use.
     *
     * @since 4.1.0
     */
    public function __construct(
        private readonly Http $http,
        private readonly string $apiKey,
        private readonly string $model = 'claude-opus-4-8'
    ) {
    }

    /**
     * Audit an extension's source for common security issues.
     *
     * @param string   $extensionName The extension name.
     * @param string   $version       The extension version being audited.
     * @param string[] $sourceFiles   Map of relative file path => file contents.
     * @param string   $phpstanSummary The phpstan report text, given as additional context.
     *
     * @return ClaudeAuditResult
     *
     * @since 4.1.0
     */
    public function audit(string $extensionName, string $version, array $sourceFiles, string $phpstanSummary): ClaudeAuditResult
    {
        if ($this->apiKey === '') {
            return new ClaudeAuditResult('Claude audit unavailable: no Anthropic API key configured.', false);
        }

        $body = json_encode(
            [
                'model'         => $this->model,
                'max_tokens'    => 8192,
                'system'        => $this->systemPrompt(),
                'output_config' => ['effort' => 'high'],
                'messages'      => [
                    ['role' => 'user', 'content' => $this->buildPrompt($extensionName, $version, $sourceFiles, $phpstanSummary)],
                ],
            ],
            \JSON_THROW_ON_ERROR
        );

        try {
            $response = $this->http->post(
                self::API_URL,
                $body,
                [
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ],
                120
            );
        } catch (Throwable $e) {
            return new ClaudeAuditResult('Claude audit unavailable: ' . $e->getMessage(), false);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return new ClaudeAuditResult(\sprintf('Claude audit unavailable: HTTP %d', $response->getStatusCode()), false);
        }

        $data = json_decode((string) $response->getBody(), true);

        $stopReason = $data['stop_reason'] ?? null;

        if ($stopReason === 'refusal') {
            return new ClaudeAuditResult('Claude declined to audit this extension.', false);
        }

        $text = $data['content'][0]['text'] ?? null;

        if (!\is_string($text) || $text === '') {
            return new ClaudeAuditResult('Claude audit returned no content.', false);
        }

        if ($stopReason === 'max_tokens') {
            $text .= "\n\n_(Report truncated: reached the maximum output length.)_";
        }

        return new ClaudeAuditResult($text, true);
    }

    /**
     * @return string The fixed security-auditor persona/instructions.
     *
     * @since 4.1.0
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
            You are a security auditor reviewing third-party Joomla extension source code before it is
            listed on the Joomla Extensions Directory. Produce a structured Markdown report with exactly
            these sections, in this order: "## SQL Injection", "## Privilege Escalation / Missing ACL
            Checks", "## Unrestricted File Uploads", "## Other Findings". Under each section, list findings
            as a bullet with a severity tag (Critical/High/Medium/Low) and a file:line reference where
            possible. If a section has no findings, write "No issues found." under it. Do not include any
            text outside of these four sections.
            PROMPT;
    }

    /**
     * @param string   $extensionName  The extension name.
     * @param string   $version        The extension version.
     * @param string[] $sourceFiles    Map of relative file path => file contents.
     * @param string   $phpstanSummary The phpstan report text.
     *
     * @return string
     *
     * @since 4.1.0
     */
    private function buildPrompt(string $extensionName, string $version, array $sourceFiles, string $phpstanSummary): string
    {
        $parts = [
            \sprintf("Extension: %s %s\n\n", $extensionName, $version),
            "PHPStan static analysis summary:\n" . $phpstanSummary . "\n\n",
            "Source files:\n",
        ];

        $bytes = 0;
        $count = 0;

        foreach ($sourceFiles as $path => $contents) {
            if ($count >= self::MAX_SOURCE_FILES || $bytes >= self::MAX_SOURCE_BYTES) {
                $parts[] = "\n(Additional files omitted to stay within the review budget.)\n";
                break;
            }

            $chunk    = \sprintf("\n--- %s ---\n%s\n", $path, $contents);
            $bytes += \strlen($chunk);
            $count++;
            $parts[]  = $chunk;
        }

        return implode('', $parts);
    }
}
