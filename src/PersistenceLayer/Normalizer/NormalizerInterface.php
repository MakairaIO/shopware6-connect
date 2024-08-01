<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface NormalizerInterface
{
    public function normalize(Entity $entity, SalesChannelContext $context): array;

    public static function getSupportedEntity(): string;
}
