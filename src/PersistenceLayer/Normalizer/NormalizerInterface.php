<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Shopware\Core\System\SalesChannel\SalesChannelContext;

interface NormalizerInterface
{
    public function normalize(string $entityId, SalesChannelContext $context): array;

    public static function getSupportedEntity(): string;
}
