<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer;

use Makaira\Connect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface LoaderInterface
{
    /**
     * @throws NotFoundException If the entity does not exist
     */
    public function load(string $entityId, SalesChannelContext $context): Entity;

    public static function getSupportedEntity(): string;
}
