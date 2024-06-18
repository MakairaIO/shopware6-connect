<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ProductManufacturerNormalizer implements NormalizerInterface
{
    use CustomFieldsTrait;

    /**
     * @param EntityRepository<ProductManufacturerCollection> $repository
     */
    public function __construct(private EntityRepository $repository)
    {
    }

    /**
     * @throws NotFoundException
     */
    public function normalize(string $entityId, SalesChannelContext $context): array
    {
        $manufacturer = $this->loadEntity($entityId, $context);

        return [
            'id' => $entityId,
            'type' => 'manufacturer',
            'manufacturer_title' => $manufacturer->getTranslation('name'),
            'customFields' => $this->processCustomFields($manufacturer->getCustomFields()),
            'active' => true,
            'timestamp' => ($manufacturer->getUpdatedAt() ?? $manufacturer->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return ProductManufacturerDefinition::ENTITY_NAME;
    }

    private function loadEntity(string $entityId, SalesChannelContext $context): ProductManufacturerEntity
    {
        $criteria = new Criteria([$entityId]);

        $entity = $this->repository->search($criteria, $context->getContext())->first();
        if (null === $entity) {
            throw NotFoundException::entity(self::getSupportedEntity(), $entityId);
        }

        return $entity;
    }
}
