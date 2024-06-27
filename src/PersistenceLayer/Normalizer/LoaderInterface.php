<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
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
