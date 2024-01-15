<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Handler;

use Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Message\UpdateEntities;
use Ixomo\MakairaConnect\PersistenceLayer\Updater;
use Ixomo\MakairaConnect\SalesChannel\ContextFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateEntitiesHandler
{
    public function __construct(private readonly Updater $updater, private readonly ContextFactory $contextFactory)
    {
    }

    public function __invoke(UpdateEntities $message): void
    {
        $this->updater->update($message->getEntityReferences(), $this->contextFactory->create($message->getSalesChannelId(), $message->getLanguageId()));
    }
}
