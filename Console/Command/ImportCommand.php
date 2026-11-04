<?php
/**
 * @author    eBusiness360 – Maxime LESGUILLIER aka Maxcode
 * @copyright Copyright © 2026 eBusiness360 – Maxime LESGUILLIER aka Maxcode. Tous droits réservés.
 * @license   Licence propriétaire — voir le fichier LICENSE
 * @package   Maxcode_Faq
 */
declare(strict_types=1);

namespace Maxcode\Faq\Console\Command;

use Magento\Framework\Console\Cli;
use Maxcode\Faq\Model\Import\Importer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * bin/magento maxcode:faq:import [--source=mageprince|mageworx|all] [--dry-run] [--convert-widgets]
 */
class ImportCommand extends Command
{
    private const OPTION_SOURCE = 'source';
    private const OPTION_DRY_RUN = 'dry-run';
    private const OPTION_WIDGETS = 'convert-widgets';

    /**
     * @param Importer    $importer Import des FAQ des autres modules.
     * @param string|null $name     Nom de la commande.
     */
    public function __construct(private readonly Importer $importer, ?string $name = null)
    {
        parent::__construct($name);
    }

    /**
     * @inheritdoc
     */
    protected function configure(): void
    {
        $this->setName('maxcode:faq:import')
            ->setDescription('Imports the FAQ of Mageprince FAQ and/or MageWorx FAQ into Maxcode FAQ (read-only on the source, safe to run again).')
            ->addOption(
                self::OPTION_SOURCE,
                's',
                InputOption::VALUE_REQUIRED,
                'Where to import from: mageprince, mageworx or all.',
                'all'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                'd',
                InputOption::VALUE_NONE,
                'Only count and report, write nothing.'
            )
            ->addOption(
                self::OPTION_WIDGETS,
                null,
                InputOption::VALUE_NONE,
                'Also replace the Mageprince FAQ widget in CMS pages and blocks by the Maxcode FAQ widget (import the groups first).'
            );
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = strtolower((string) $input->getOption(self::OPTION_SOURCE));
        $sources = match ($source) {
            'all' => [Importer::SOURCE_MAGEPRINCE, Importer::SOURCE_MAGEWORX],
            Importer::SOURCE_MAGEPRINCE, Importer::SOURCE_MAGEWORX => [$source],
            default => [],
        };
        if (!$sources) {
            $output->writeln('<error>--source must be mageprince, mageworx or all.</error>');

            return Cli::RETURN_FAILURE;
        }
        $dryRun = (bool) $input->getOption(self::OPTION_DRY_RUN);
        $convert = (bool) $input->getOption(self::OPTION_WIDGETS);

        try {
            $result = $this->importer->run($sources, $dryRun, $convert);
        } catch (\Throwable $exception) {
            $output->writeln('<error>' . OutputFormatter::escape($exception->getMessage()) . '</error>');

            return Cli::RETURN_FAILURE;
        }

        $verb = $dryRun ? 'would be created' : 'created';
        foreach ($result['sources'] as $name => $data) {
            $output->writeln('<info>' . $name . '</info>');
            if (!$data['available']) {
                $output->writeln('  not installed here (its tables were not found): nothing to import.');
                continue;
            }
            $report = $data['report'];
            $output->writeln(sprintf(
                '  groups: %d %s, %d already imported',
                $report['groups_created'],
                $verb,
                $report['groups_existing']
            ));
            $output->writeln(sprintf(
                '  questions: %d %s, %d already imported',
                $report['questions_created'],
                $verb,
                $report['questions_existing']
            ));
            foreach ($data['notes'] as $note) {
                $output->writeln('  note: ' . OutputFormatter::escape($note));
            }
            foreach ($data['warnings'] as $warning) {
                $output->writeln('  <comment>warning: ' . OutputFormatter::escape($warning) . '</comment>');
            }
        }
        if ($result['widgets'] !== null) {
            $widgets = $result['widgets'];
            $output->writeln('<info>widgets</info>');
            $output->writeln(sprintf(
                '  %d Mageprince widget(s) found in CMS pages and blocks, %d %s',
                $widgets['found'],
                $widgets['converted'],
                $dryRun ? 'would be converted' : 'converted'
            ));
            foreach ($widgets['lines'] as $line) {
                $output->writeln('  ' . OutputFormatter::escape($line));
            }
            foreach ($widgets['warnings'] as $warning) {
                $output->writeln('  <comment>warning: ' . OutputFormatter::escape($warning) . '</comment>');
            }
        }
        if ($result['page_url'] !== '') {
            $output->writeln(sprintf(
                'Mageprince published its FAQ page at "%s": set the same address under Stores > Configuration > Maxcode > FAQ > FAQ page to keep the address (after disabling Mageprince FAQ).',
                OutputFormatter::escape($result['page_url'])
            ));
        }
        if ($dryRun) {
            $output->writeln('Dry run: nothing was written.');
        } else {
            $output->writeln('Imported questions keep their active/draft status. Run bin/magento cache:flush to see them on the store.');
        }

        return Cli::RETURN_SUCCESS;
    }
}
