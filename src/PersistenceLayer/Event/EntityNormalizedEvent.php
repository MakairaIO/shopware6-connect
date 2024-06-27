<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Event;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Event\GenericEvent;
use Symfony\Contracts\EventDispatcher\Event;

final class EntityNormalizedEvent extends Event implements GenericEvent
{
    private readonly string $name;
    private bool $dataChanged = false;

    public function __construct(
        private readonly string $entityName,
        private readonly Entity $entity,
        private array $data,
    ) {
        $this->name = 'ixomo.makaira_connect.' . $entityName . '.normalized';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEntity(): Entity
    {
        return $this->entity;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function replaceData(array $data): void
    {
        $this->data = $data;
        $this->dataChanged = true;
    }

    public function patchData(array $data): void
    {
        $this->data = array_merge($this->data, $data);
        $this->dataChanged = true;
    }

    public function wasDataChanged(): bool
    {
        return $this->dataChanged;
    }
}
