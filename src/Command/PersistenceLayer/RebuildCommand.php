<?php

declare(strict_types=1);

namespace Makaira\Connect\Command\PersistenceLayer;

use Makaira\Connect\Api\ApiGatewayFactory;
use Makaira\Connect\PersistenceLayer\History\HistoryManager;
use Makaira\Connect\SalesChannel\ContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'makaira:persistence-layer:rebuild', description: 'Initialize rebuild of the Makaira persistence layer')]
final class RebuildCommand extends Command
{
    public function __construct(
        private readonly ApiGatewayFactory $apiGatewayFactory,
        private readonly HistoryManager $historyManager,
        private readonly ContextFactory $contextFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('salesChannelId', InputArgument::REQUIRED, 'Sales channel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = $this->contextFactory->create($input->getArgument('salesChannelId'));

        $io->title('Initialize rebuild for sales-channel "' . $context->getSalesChannel()->getName() . '"');

        try {
            $this->apiGatewayFactory->create($context)->rebuildPersistenceLayer();
            $this->historyManager->clear($context);

            $io->success('Finished, you can now import your data');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
