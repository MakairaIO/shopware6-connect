<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\MessageQueue\Message;

use Ixomo\MakairaConnect\PersistenceLayer\EntityReference;
use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

final class DeleteEntities implements AsyncMessageInterface
{
    /**
     * @param list<EntityReference> $entityReferences
     */
    public function __construct(
        private readonly array $entityReferences,
        private readonly string $salesChannelId,
        private readonly string $languageId,
    ) {
    }

    /**
     * @return list<EntityReference>
     */
    public function getEntityReferences(): array
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
