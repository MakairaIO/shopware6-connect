<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Event\ProductManufacturerLoaderCriteriaEvent;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ProductManufacturerLoader implements LoaderInterface
{
    use CustomFieldsTrait;

    /**
     * @param EntityRepository<ProductManufacturerCollection> $repository
     */
    public function __construct(
        private EntityRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $entityId, SalesChannelContext $context): ProductManufacturerEntity
    {
        $criteria = new Criteria([$entityId]);

        $this->eventDispatcher->dispatch(new ProductManufacturerLoaderCriteriaEvent($criteria, $context));

        $entity = $this->repository->search($criteria, $context->getContext())->first();
        if (null === $entity) {
            throw NotFoundException::entity(self::getSupportedEntity(), $entityId);
        }

        return $entity;
    }

    public static function getSupportedEntity(): string
    {
        return ProductManufacturerDefinition::ENTITY_NAME;
    }
}
