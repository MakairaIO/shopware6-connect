<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer;

use Shopware\Core\Framework\Struct\Struct;

final class EntityReference extends Struct
{
    public function __construct(
        protected readonly string $entityName,
        protected readonly string $entityId,
    ) {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }
}
