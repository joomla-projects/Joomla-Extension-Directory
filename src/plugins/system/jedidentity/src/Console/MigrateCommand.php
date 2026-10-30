<?php

/**
 * @package     Joomla.Plugin
 * @subpackage  System.jedidentity
 *
 * @copyright   (C) 2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\System\Jedidentity\Console;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Identity\AccountMigrator;
use Jed\Component\Jed\Administrator\Identity\PortalExport;
use Joomla\Console\Command\AbstractCommand;
use Joomla\Database\DatabaseInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `php cli/joomla.php jed:identity:migrate export.csv [--apply]`
 *
 * Moves old JED accounts onto the GUIDs the portal gave the same people, matching by email address.
 * Without `--apply` it only reports, so the run can be read first and repeated as often as needed.
 * With `-v` every account is listed.
 *
 * @since 4.0.0
 */
final class MigrateCommand extends AbstractCommand
{
    /**
     * @var string
     *
     * @since 4.0.0
     */
    protected static $defaultName = 'jed:identity:migrate';

    /**
     * @param DatabaseInterface $db The database.
     *
     * @since 4.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
        parent::__construct();
    }

    /**
     * @return void
     *
     * @since 4.0.0
     */
    protected function configure(): void
    {
        $this->setDescription('Move old JED accounts onto their Identity Portal GUIDs, matching by email address');
        $this->setHelp('The CSV needs a guid column and an email (or emailaddress) column. Without --apply nothing is written.');
        $this->addArgument('file', InputArgument::REQUIRED, 'The portal export (CSV)');
        $this->addOption('apply', null, InputOption::VALUE_NONE, 'Write the changes (the default only reports them)');
    }

    /**
     * @param InputInterface  $input  The input.
     * @param OutputInterface $output The output.
     *
     * @return integer
     *
     * @since 4.0.0
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $file = (string) $input->getArgument('file');

        if (!is_file($file) || !is_readable($file)) {
            $io->error('Cannot read ' . $file);

            return 1;
        }

        $export = PortalExport::parse((string) file_get_contents($file));

        foreach ($export['errors'] as $error) {
            $io->warning($error);
        }

        if (!$export['rows']) {
            $io->error('The file holds no usable rows.');

            return 1;
        }

        $migrator = new AccountMigrator($this->db);
        $plan     = $migrator->plan($export['rows']);
        $reasons  = array_count_values(array_column($plan['skipped'], 'reason'));
        $blocked  = \count(array_filter($plan['rename'], static fn (array $r): bool => $r['blocked']));

        $io->title('Migration of old accounts to portal GUIDs');
        $io->table(
            ['', 'People'],
            array_merge(
                [['In the export', \count($export['rows'])], ['Accounts to rename', \count($plan['rename'])], ['  of which blocked accounts', $blocked]],
                array_map(static fn (string $reason, int $n): array => ['Left out: ' . $reason, $n], array_keys($reasons), $reasons)
            )
        );

        if ($output->isVerbose()) {
            foreach ($plan['rename'] as $r) {
                $io->writeln(sprintf('rename  #%d  %s  ->  %s', $r['user_id'], $r['from'], $r['guid']));
            }

            foreach ($plan['skipped'] as $s) {
                $io->writeln(sprintf('skip    %s  %s  (%s)', $s['guid'], $s['email'], $s['reason']));
            }
        }

        if (!$input->getOption('apply')) {
            $io->note('Nothing was written. Run again with --apply to rename these accounts.');

            return 0;
        }

        $report = $migrator->apply($plan['rename']);

        foreach ($report['failed'] as $failure) {
            $io->warning(sprintf('#%d (%s): %s', $failure['user_id'], $failure['guid'], $failure['why']));
        }

        $io->success(sprintf('%d accounts renamed, %d failed.', $report['done'], \count($report['failed'])));

        return $report['failed'] ? 2 : 0;
    }
}
