<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception;

class NotFoundException extends \Exception
{
    public static function entity(string $entityName, string $entityId): self
    {
        return new self('The entity ' . $entityName . ' with ID ' . $entityId . ' was not found');
    }
}
