<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer\Exception;

use Makaira\Connect\PersistenceLayer\EntityReference;

class InvalidDataException extends \Exception
{
    public static function missingTypeOrId(EntityReference $entityReference): self
    {
        return new self(sprintf(
            'The normalized data for the entity %s with ID %s must contain an ID and type',
            $entityReference->getEntityName(),
            $entityReference->getEntityId()
        ));
    }
}
