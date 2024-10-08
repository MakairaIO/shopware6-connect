<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\ScheduledTask;

use Makaira\Connect\PersistenceLayer\EntityRepository;
use Makaira\Connect\PersistenceLayer\MessageQueue\Message\UpdateEntities;
use Makaira\Connect\PluginConfig;
use Makaira\Connect\SalesChannel\ContextFactory;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository as ShopwareEntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(handles: UpdateTask::class)]
final class UpdateTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        ShopwareEntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly EntityRepository $entityRepository,
        private readonly MessageBusInterface $bus,
        private readonly ContextFactory $contextFactory,
        private readonly ClockInterface $clock,
        private readonly PluginConfig $config,
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        foreach ($this->contextFactory->createAll(Context::createDefaultContext(), true) as $context) {
            $lastUpdate = $this->config->getLastPersistenceLayerUpdate($context->getSalesChannelId());
            $now = $this->clock->now();

            $modifiedEntities = $this->entityRepository->findModified($lastUpdate, $context);

            if (0 < \count($modifiedEntities)) {
                foreach ($modifiedEntities->chunk(25) as $chunk) {
                    $this->bus->dispatch(new UpdateEntities($chunk->getElements(), $context->getSalesChannelId(), $context->getLanguageId()));
                }
            }

            $this->config->setLastPersistenceLayerUpdate($now, $context->getSalesChannelId());
        }
    }

    public static function getHandledMessages(): iterable
    {
        return [UpdateTask::class];
    }
}
