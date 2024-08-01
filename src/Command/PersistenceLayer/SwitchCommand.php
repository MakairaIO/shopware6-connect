<?php

declare(strict_types=1);

namespace Makaira\Connect\Command\PersistenceLayer;

use Makaira\Connect\Api\ApiGatewayFactory;
use Makaira\Connect\SalesChannel\ContextFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'makaira:persistence-layer:switch', description: 'Use the rebuild data as active data for the Makaira persistence layer')]
final class SwitchCommand extends Command
{
    public function __construct(
        private readonly ApiGatewayFactory $apiGatewayFactory,
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

        $io->title('Switch persistence layer data for sales-channel "' . $context->getSalesChannel()->getName() . '"');

        try {
            $this->apiGatewayFactory->create($context)->switchPersistenceLayer();

            $io->success('Finished');

            return Command::SUCCESS;
        } catch (\Throwable $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
    }
}
