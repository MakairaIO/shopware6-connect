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

#[AsCommand(name: 'ixomo:makaira-connect:persistence-layer:history:clear', description: 'Clear the history of the Makaira Persistence Layer')]
final class ClearCommand extends Command
{
    public function __construct(private readonly HistoryManager $historyManager, private readonly ContextFactory $contextFactory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('salesChannelId', InputArgument::REQUIRED, 'Sales channel')
            ->addOption('languageId', null, InputOption::VALUE_REQUIRED, 'Language-ID (if omitted, the default language of the sales channel is used)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->contextFactory->create($input->getArgument('salesChannelId'), $input->getOption('languageId'));

        $this->historyManager->clear($context);

        return Command::SUCCESS;
    }
}
