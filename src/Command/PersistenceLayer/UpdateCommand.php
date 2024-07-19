<?php

declare(strict_types=1);

namespace Makaira\Connect\Command\PersistenceLayer;

use Makaira\Connect\PersistenceLayer\EntityReference;
use Makaira\Connect\PersistenceLayer\EntityReferenceCollection;
use Makaira\Connect\PersistenceLayer\EntityRepository;
use Makaira\Connect\PersistenceLayer\Updater;
use Makaira\Connect\PluginConfig;
use Makaira\Connect\SalesChannel\ContextFactory;
use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'makaira:persistence-layer:update', description: 'Update the Makaira persistence layer')]
final class UpdateCommand extends Command
{
    public function __construct(
        private readonly EntityRepository $repository,
        private readonly Updater $updater,
        private readonly ContextFactory $contextFactory,
        private readonly ClockInterface $clock,
        private readonly PluginConfig $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('salesChannelId', InputArgument::REQUIRED, 'Sales channel')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Language-ID (if omitted, the default language of the sales channel is used)')
            ->addOption('modified', null, InputOption::VALUE_NONE, 'Update only modified entities')
            ->addOption('only', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Update only specific entities (format: <entity-name>:<entity-id>)')
            ->addOption('chunkSize', null, InputOption::VALUE_REQUIRED, 'Chunk size (maximum number of records per API request)', 250);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = $this->contextFactory->create($input->getArgument('salesChannelId'), $input->getOption('languageId'));

        $io->title('Updata the Makaira persistence layer for sales-channel "' . $context->getSalesChannel()->getName() . '"');

        if (0 < \count($input->getOption('only'))) {
            $io->text('Process selected records...');

            $entities = new EntityReferenceCollection(
                array_map(function (string $value): EntityReference {
                    if (!preg_match('/^[a-z_]+:[a-z0-9]{32}$/', $value)) {
                        throw new \InvalidArgumentException(
                            'Invalid entity-reference, expected "<entity-name>:<entity-id>", got "' . $value . '"'
                        );
                    }

                    [$entityName, $entityId] = explode(':', $value);

                    return new EntityReference($entityName, $entityId);
                }, $input->getOption('only'))
            );
        } elseif ($input->getOption('modified')) {
            $io->text('Process only modified records...');

            $lastUpdate = $this->config->getLastPersistenceLayerUpdate($context->getSalesChannelId());
            $now = $this->clock->now();

            $entities = $this->repository->findModified($lastUpdate, $context);
        } else {
            $io->text('Process all records...');

            $entities = $this->repository->findAll($context);
        }

        $chunkSize = (int) $input->getOption('chunkSize');

        $io->text('Found ' . \count($entities) . ' record(s)...');

        if (0 < \count($entities)) {
            $io->text('Set chunk-size for API calls to ' . $chunkSize . ' record(s)...');
            $io->newLine();

            $io->progressStart(\count($entities));
            foreach ($entities->chunk($chunkSize) as $chunk) {
                $this->updater->update($chunk, $context);

                $io->progressAdvance(\count($chunk));
            }
            $io->progressFinish();
        }

        if ($input->getOption('modified') && isset($now)) {
            $this->config->setLastPersistenceLayerUpdate($now, $context->getSalesChannelId());
        }

        $io->success('Finished');

        return Command::SUCCESS;
    }
}
