<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer;

use Makaira\Connect\PersistenceLayer\Normalizer\Exception\UnsupportedException;

final class NormalizerRegistry
{
    /**
     * @var array<string, NormalizerInterface>
     */
    private readonly array $normalizers;

    /**
     * @param iterable<string, NormalizerInterface> $normalizers
     */
    public function __construct(iterable $normalizers)
    {
        $this->normalizers = $normalizers instanceof \Traversable ? iterator_to_array($normalizers) : $normalizers;
    }

    public function getNormalizer(string $entityName): NormalizerInterface
    {
        if (!\array_key_exists($entityName, $this->normalizers)) {
            throw UnsupportedException::entity($entityName);
        }

        return $this->normalizers[$entityName];
    }
}
