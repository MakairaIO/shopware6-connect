<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer;

use Ixomo\MakairaConnect\PersistenceLayer\Event\FindAllEntitiesCriteriaEvent;
use Ixomo\MakairaConnect\PersistenceLayer\Event\FindModifiedEntitiesCriteriaEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository as ShopwareEntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class EntityRepository
{
    /**
     * @param array<string, ShopwareEntityRepository|SalesChannelRepository> $repositories
     */
    public function __construct(
        private readonly array $repositories,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function findAll(SalesChannelContext $context): EntityReferenceCollection
    {
        $references = new EntityReferenceCollection();

        foreach ($this->repositories as $entityName => $repository) {
            $criteria = new Criteria();

            $this->eventDispatcher->dispatch(new FindAllEntitiesCriteriaEvent($criteria, $context));

            $searchResult = $repository instanceof SalesChannelRepository
                ? $repository->searchIds($criteria, $context)
                : $repository->searchIds($criteria, $context->getContext());

            foreach ($searchResult->getIds() as $entityId) {
                $references->add(new EntityReference($entityName, $entityId));
            }
        }

        return $references;
    }

    public function findModified(?\DateTimeInterface $lastUpdate, SalesChannelContext $context): EntityReferenceCollection
    {
        $references = new EntityReferenceCollection();

        foreach ($this->repositories as $entityName => $repository) {
            $criteria = new Criteria();

            if (null !== $lastUpdate) {
                $lastUpdateFormatted = $lastUpdate->format(Defaults::STORAGE_DATE_TIME_FORMAT);

                $criteria->addFilter(new OrFilter([
                    new RangeFilter('createdAt', [
                        RangeFilter::GTE => $lastUpdateFormatted,
                    ]),
                    new RangeFilter('updatedAt', [
                        RangeFilter::GTE => $lastUpdateFormatted,
                    ]),
                ]));
            }

            $this->eventDispatcher->dispatch(new FindModifiedEntitiesCriteriaEvent($criteria, $context));

            $searchResult = $repository instanceof SalesChannelRepository
                ? $repository->searchIds($criteria, $context)
                : $repository->searchIds($criteria, $context->getContext());

            foreach ($searchResult->getIds() as $entityId) {
                $references->add(new EntityReference($entityName, $entityId));
            }
        }

        return $references;
    }
}
