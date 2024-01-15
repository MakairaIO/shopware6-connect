<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Command\PersistenceLayer\History;

use Ixomo\MakairaConnect\PersistenceLayer\History\HistoryManager;
use Ixomo\MakairaConnect\SalesChannel\ContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'ixomo:makaira-connect:persistence-layer:history:garbage-collector', description: 'Garabge collector for the history of the Makaira Persistence Layer')]
final class GarbageCollectorCommand extends Command
{
    public function __construct(
        private readonly HistoryManager $historyManager,
        private readonly ContextFactory $contextFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('salesChannelId', InputArgument::REQUIRED, 'Sales channel')
            ->addArgument('keep', InputArgument::REQUIRED, 'Number of items per record to keep')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Language-ID (if omitted, the default language of the sales channel is used)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->contextFactory->create($input->getArgument('salesChannelId'), $input->getOption('languageId'));

        $this->historyManager->garbageCollector((int) $input->getArgument('keep'), $context);

        return Command::SUCCESS;
    }
}
