<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Message;

use Ixomo\MakairaConnect\PersistenceLayer\EntityReferenceCollection;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class UpdateEntities implements AsyncMessageInterface
{
    public function __construct(
        private readonly EntityReferenceCollection $entityReferences,
        private readonly string $salesChannelId,
        private readonly string $languageId,
    ) {
    }

    public function getEntityReferences(): EntityReferenceCollection
    {
        return $this->entityReferences;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }
}
