<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Event\ProductLoaderCriteriaEvent;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ProductLoader implements LoaderInterface
{
    /**
     * @param SalesChannelRepository<ProductCollection> $repository
     */
    public function __construct(
        private SalesChannelRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $entityId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria([$entityId]);
        $criteria->addAssociation('media.media');
        $criteria->addAssociation('configuratorSettings');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('tags');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('searchKeywords');

        $this->eventDispatcher->dispatch(new ProductLoaderCriteriaEvent($criteria, $context));

        $entity = $this->repository->search($criteria, $context)->first();
        if (null === $entity) {
            throw NotFoundException::entity(self::getSupportedEntity(), $entityId);
        }

        return $entity;
    }

    public static function getSupportedEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }
}
