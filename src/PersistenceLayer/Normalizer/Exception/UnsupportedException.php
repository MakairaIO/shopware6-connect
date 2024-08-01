<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer\Exception;

class UnsupportedException extends \Exception
{
    public static function entity(string $entityName): self
    {
        return new self('The entity ' . $entityName . ' is not supported');
    }
}
