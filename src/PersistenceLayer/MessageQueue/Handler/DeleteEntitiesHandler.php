<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Handler;

use Ixomo\MakairaConnect\PersistenceLayer\EntityReferenceCollection;
use Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Message\DeleteEntities;
use Ixomo\MakairaConnect\PersistenceLayer\Updater;
use Ixomo\MakairaConnect\SalesChannel\ContextFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteEntitiesHandler
{
    public function __construct(private Updater $updater, private ContextFactory $contextFactory)
    {
    }
    public function __invoke(DeleteEntities $message): void
    {
        $this->updater->delete(
            new EntityReferenceCollection($message->getEntityReferences()),
            $this->contextFactory->create($message->getSalesChannelId(), $message->getLanguageId())
        );
    }
}
