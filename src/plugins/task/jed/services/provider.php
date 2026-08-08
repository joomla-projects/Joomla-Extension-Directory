<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.jed
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Jed\Component\Abandonware\Administrator\Service\CaseService;
use Jed\Component\Abandonware\Administrator\Service\SignalScanner;
use Jed\Component\Jed\Administrator\Audit\AuditPipeline;
use Jed\Component\Jed\Administrator\Audit\ClaudeAuditor;
use Jed\Component\Jed\Administrator\Audit\DockerRunner;
use Jed\Component\Jed\Administrator\Audit\ProcessRunner;
use Jed\Component\Jed\Administrator\Hit\HitAggregator;
use Jed\Component\Jed\Administrator\Link\LinkCheckService;
use Jed\Component\Jed\Administrator\Queue\AuditJobHandler;
use Jed\Component\Jed\Administrator\Privacy\PrivacyRetentionService;
use Jed\Component\Jed\Administrator\Queue\HitAggregateJobHandler;
use Jed\Component\Jed\Administrator\Queue\JobHandlerRegistry;
use Jed\Component\Jed\Administrator\Queue\LinkCheckJobHandler;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Queue\ScoreRecalcJobHandler;
use Jed\Component\Jed\Administrator\Service\ExtensionVersionUpdater;
use Jed\Component\Jed\Administrator\Service\ScoreCalculationService;
use Jed\Component\Jed\Administrator\Service\UpdateCheckService;
use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
use Jed\Component\Jed\Administrator\Url\SafeHttpFetcher;
use Jed\Component\Jed\Administrator\Url\UrlValidatorRegistry;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseInterface;
use Joomla\Http\HttpFactory;
use Joomla\Plugin\Task\Jed\Extension\Jed;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the service provider with a DI container.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Jed::class, function (Container $container) {
                $db     = $container->get(DatabaseInterface::class);
                $http   = (new HttpFactory())->getHttp();
                $params = ComponentHelper::getParams('com_jed');

                $queueService   = new QueueService($db);
                $versionUpdater = new ExtensionVersionUpdater($db);
                // The update check fetches a URL a developer typed, so it goes through the
                // guarded fetcher (P1-08) rather than the plain HTTP client. $http stays for the
                // Anthropic call below, which talks to one fixed endpoint we control.
                $updateCheck    = new UpdateCheckService(
                    $db,
                    new SafeHttpFetcher(),
                    new UpdateServerXmlParser(),
                    $versionUpdater,
                    $queueService
                );

                $dockerRunner = new DockerRunner(
                    new ProcessRunner(),
                    (string) $params->get('docker_binary_path', 'docker'),
                    'jed-audit:latest'
                );
                $claudeAuditor = new ClaudeAuditor(
                    $http,
                    (string) $params->get('anthropic_api_key', ''),
                    (string) $params->get('anthropic_model', 'claude-opus-4-8')
                );
                $auditPipeline = new AuditPipeline(
                    $db,
                    $http,
                    $dockerRunner,
                    $claudeAuditor,
                    JPATH_ADMINISTRATOR . '/components/com_jed/audit-workspace',
                    JPATH_ADMINISTRATOR . '/components/com_jed/reports',
                    900
                );

                $scoreCalculationService = new ScoreCalculationService($db);

                // One fetcher and one validator registry for both the periodic pass and the
                // on-demand job - the same ones the form uses (P1-08). P1-09 owns no validators.
                $hitAggregator = new HitAggregator($db);

                $linkCheckService = new LinkCheckService(
                    $db,
                    UrlValidatorRegistry::withDefaults(new SafeHttpFetcher(), new UpdateServerXmlParser())
                );

                $jobHandlerRegistry = new JobHandlerRegistry();
                $jobHandlerRegistry->register('extension.audit', new AuditJobHandler($auditPipeline));
                $jobHandlerRegistry->register('extension.score_recalc', new ScoreRecalcJobHandler($scoreCalculationService));
                $jobHandlerRegistry->register('extension.linkcheck', new LinkCheckJobHandler($linkCheckService));
                $jobHandlerRegistry->register('hits.aggregate', new HitAggregateJobHandler($hitAggregator));

                // P1-19's case object. Built here rather than resolved from com_abandonware's own
                // container for the same reason the ticket handler registry is a plain factory:
                // the scheduler boots no component, so nothing has registered that container.
                $signalScanner = new SignalScanner($db, new CaseService($db));

                $plugin = new Jed(
                    (array) PluginHelper::getPlugin('task', 'jed'),
                    $updateCheck,
                    $queueService,
                    $jobHandlerRegistry,
                    $linkCheckService,
                    $hitAggregator,
                    $signalScanner,
                    new PrivacyRetentionService($db)
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
