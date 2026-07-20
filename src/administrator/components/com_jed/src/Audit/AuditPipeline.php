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

use FilesystemIterator;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Mail\MailTemplate;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Folder;
use Joomla\Http\Http;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/**
 * Orchestrates one `extension.audit` queue job: downloads the extension, runs it
 * through the sandboxed Docker/phpstan/Claude pipeline, persists both reports, and
 * emails them to the extension's developer via the `com_jed.audit_report` mail
 * template.
 *
 * @since 4.1.0
 */
class AuditPipeline
{
    /**
     * @param DatabaseInterface $db                    The database connector object.
     * @param Http               $http                 The HTTP client used to download the extension zip.
     * @param DockerRunner       $dockerRunner          Runs the sandboxed audit container.
     * @param ClaudeAuditor      $claudeAuditor         Runs the Claude security review.
     * @param string             $workspaceRoot         Directory for transient per-job input/output.
     * @param string             $reportsRoot           Directory where persisted reports are written.
     * @param int                $dockerTimeoutSeconds  Maximum time to allow the audit container to run.
     *
     * @since 4.1.0
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly Http $http,
        private readonly DockerRunner $dockerRunner,
        private readonly ClaudeAuditor $claudeAuditor,
        private readonly string $workspaceRoot,
        private readonly string $reportsRoot,
        private readonly int $dockerTimeoutSeconds = 900
    ) {
    }

    /**
     * Run the pipeline for one queued audit job.
     *
     * @param object $job The `#__jed_queue_jobs` row (id, extension_id, history_id, payload).
     *
     * @return array{phpstan_report_path: string, claude_report_path: string, docker_exit_code: int}
     *
     * @throws RuntimeException If the extension cannot be loaded or has no download URL.
     *
     * @since 4.1.0
     */
    public function run(object $job): array
    {
        $extensionId = (int) $job->extension_id;
        $extension   = $this->loadExtension($extensionId);

        if ($extension === null) {
            throw new RuntimeException(\sprintf('Extension #%d not found for audit job #%d.', $extensionId, $job->id));
        }

        $payload = $job->payload ? json_decode((string) $job->payload, true) : [];
        $version = $payload['version'] ?? $extension->extension_version;

        $jobWorkspace = rtrim($this->workspaceRoot, '/\\') . '/' . $job->id . '-' . uniqid();
        $inputDir     = $jobWorkspace . '/input';
        $outputDir    = $jobWorkspace . '/output';

        Folder::create($inputDir);
        Folder::create($outputDir);

        try {
            $this->downloadExtensionZip((string) $extension->download_url, $inputDir . '/extension.zip');

            $dockerResult = $this->dockerRunner->runAuditContainer($inputDir, $outputDir, $this->dockerTimeoutSeconds);

            $phpstanReportText = is_file($outputDir . '/phpstan.txt')
                ? (string) file_get_contents($outputDir . '/phpstan.txt')
                : trim($dockerResult->stdout . "\n" . $dockerResult->stderr);

            $sourceFiles  = $this->collectSourceFiles($outputDir . '/src');
            $claudeResult = $this->claudeAuditor->audit((string) $extension->name, (string) $version, $sourceFiles, $phpstanReportText);

            $reportDir = rtrim($this->reportsRoot, '/\\') . '/' . $job->id;
            Folder::create($reportDir);

            $phpstanReportPath = $reportDir . '/phpstan.txt';
            file_put_contents($phpstanReportPath, $phpstanReportText);

            $claudeReportPath = $reportDir . '/claude-security-report.md';
            file_put_contents($claudeReportPath, $claudeResult->reportMarkdown);

            $this->sendReportEmail($extension, (string) $version, $phpstanReportText, $claudeResult->reportMarkdown);

            return [
                'phpstan_report_path' => $phpstanReportPath,
                'claude_report_path'  => $claudeReportPath,
                'docker_exit_code'    => $dockerResult->exitCode,
            ];
        } finally {
            Folder::delete($jobWorkspace);
        }
    }

    /**
     * @param int $id The extension id.
     *
     * @return object|null
     *
     * @since 4.1.0
     */
    private function loadExtension(int $id): ?object
    {
        $db = $this->db;

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__jed_extensions'))
            ->where($db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        return $db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * @param string $url         The extension zip download URL.
     * @param string $destination The host-side path to save it to.
     *
     * @return void
     *
     * @throws RuntimeException If the URL is empty or the download fails.
     *
     * @since 4.1.0
     */
    private function downloadExtensionZip(string $url, string $destination): void
    {
        if ($url === '') {
            throw new RuntimeException('Extension has no download URL.');
        }

        $response = $this->http->get($url, [], 60);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException(\sprintf('Failed to download extension zip: HTTP %d', $response->getStatusCode()));
        }

        file_put_contents($destination, (string) $response->getBody());
    }

    /**
     * Read back the extension source the container copied to /audit/output/src.
     *
     * @param string $sourceDir The host-side path to the container's output/src directory.
     *
     * @return string[] Map of relative file path => file contents.
     *
     * @since 4.1.0
     */
    private function collectSourceFiles(string $sourceDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $relative          = ltrim(str_replace($sourceDir, '', $file->getPathname()), '/\\');
            $files[$relative]  = (string) file_get_contents($file->getPathname());
        }

        return $files;
    }

    /**
     * Email the combined reports to the extension's developer via the
     * `com_jed.audit_report` mail template. Failures are logged, not thrown, so a
     * mail-transport problem doesn't fail an otherwise-successful audit job.
     *
     * @param object $extension            The live extension row.
     * @param string $version              The audited version.
     * @param string $phpstanReportText    The phpstan report text.
     * @param string $claudeReportMarkdown The Claude security report Markdown.
     *
     * @return void
     *
     * @since 4.1.0
     */
    private function sendReportEmail(object $extension, string $version, string $phpstanReportText, string $claudeReportMarkdown): void
    {
        if (empty($extension->developer_email)) {
            return;
        }

        $app      = Factory::getApplication();
        $language = $app->getLanguage()->getTag();

        try {
            $mailer = new MailTemplate('com_jed.audit_report', $language);
            $mailer->addRecipient($extension->developer_email, $extension->name ?: $extension->developer_email);
            $mailer->addTemplateData([
                'sitename'         => $app->get('sitename'),
                'extensionname'    => $extension->name,
                'extensionversion' => $version,
                'phpstanreport'    => $phpstanReportText,
                'claudereport'     => $claudeReportMarkdown,
            ]);
            $mailer->addUnsafeTags(['phpstanreport', 'claudereport']);
            $mailer->send();
        } catch (Throwable $e) {
            Log::add(
                \sprintf('Failed to send audit report email for extension #%d: %s', $extension->id, $e->getMessage()),
                Log::WARNING,
                'com_jed'
            );
        }
    }
}
