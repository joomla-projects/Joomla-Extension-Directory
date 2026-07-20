<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  Task.jed
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Jed\Component\Jed\Administrator\Audit\AuditPipeline;
use Jed\Component\Jed\Administrator\Audit\ClaudeAuditor;
use Jed\Component\Jed\Administrator\Audit\DockerRunner;
use Jed\Component\Jed\Administrator\Audit\ProcessRunner;
use Jed\Component\Jed\Administrator\Queue\AuditJobHandler;
use Jed\Component\Jed\Administrator\Queue\JobHandlerRegistry;
use Jed\Component\Jed\Administrator\Queue\QueueService;
use Jed\Component\Jed\Administrator\Queue\ScoreRecalcJobHandler;
use Jed\Component\Jed\Administrator\Service\ExtensionVersionUpdater;
use Jed\Component\Jed\Administrator\Service\ScoreCalculationService;
use Jed\Component\Jed\Administrator\Service\UpdateCheckService;
use Jed\Component\Jed\Administrator\Update\UpdateServerXmlParser;
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
     * @since   4.1.0
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
                $updateCheck    = new UpdateCheckService($db, $http, new UpdateServerXmlParser(), $versionUpdater, $queueService);

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

                $jobHandlerRegistry = new JobHandlerRegistry();
                $jobHandlerRegistry->register('extension.audit', new AuditJobHandler($auditPipeline));
                $jobHandlerRegistry->register('extension.score_recalc', new ScoreRecalcJobHandler($scoreCalculationService));

                $plugin = new Jed(
                    (array) PluginHelper::getPlugin('task', 'jed'),
                    $updateCheck,
                    $queueService,
                    $jobHandlerRegistry
                );
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
