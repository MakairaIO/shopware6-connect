<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\MessageQueue\Handler;

use Makaira\Connect\PersistenceLayer\EntityReferenceCollection;
use Makaira\Connect\PersistenceLayer\MessageQueue\Message\UpdateEntities;
use Makaira\Connect\PersistenceLayer\Updater;
use Makaira\Connect\SalesChannel\ContextFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateEntitiesHandler
{
    public function __construct(private Updater $updater, private ContextFactory $contextFactory)
    {
    }

    public function __invoke(UpdateEntities $message): void
    {
        $this->updater->update(
            new EntityReferenceCollection($message->getEntityReferences()),
            $this->contextFactory->create($message->getSalesChannelId(), $message->getLanguageId())
        );
    }
}
