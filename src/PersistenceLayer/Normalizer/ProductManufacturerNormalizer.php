<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer;

use Makaira\Connect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ProductManufacturerNormalizer implements NormalizerInterface
{
    use CustomFieldsTrait;

    public function normalize(Entity $entity, SalesChannelContext $context): array
    {
        \assert($entity instanceof ProductManufacturerEntity);

        return [
            'id' => $entity->getId(),
            'type' => 'manufacturer',
            'manufacturer_title' => $entity->getTranslation('name'),
            'customFields' => $this->processCustomFields($entity->getCustomFields()),
            'active' => true,
            'timestamp' => ($entity->getUpdatedAt() ?? $entity->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return ProductManufacturerDefinition::ENTITY_NAME;
    }
}
