<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\UnsupportedException;

final class LoaderRegistry
{
    /**
     * @var array<string, LoaderInterface>
     */
    private readonly array $loaders;

    /**
     * @param iterable<string, LoaderInterface> $loaders
     */
    public function __construct(iterable $loaders)
    {
        $this->loaders = $loaders instanceof \Traversable ? iterator_to_array($loaders) : $loaders;
    }

    public function getLoader(string $entityName): LoaderInterface
    {
        if (!\array_key_exists($entityName, $this->loaders)) {
            throw UnsupportedException::entity($entityName);
        }

        return $this->loaders[$entityName];
    }
}
