<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<EntityReference>
 */
final class EntityReferenceCollection extends Collection
{
    public function chunk(int $length, bool $preserveKeys = false): iterable
    {
        foreach (array_chunk($this->elements, $length, $preserveKeys) as $chunk) {
            yield $this->createNew($chunk);
        }
    }

    protected function getExpectedClass(): string
    {
        return EntityReference::class;
    }
}
