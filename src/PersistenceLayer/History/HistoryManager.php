<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\History;

use Ixomo\MakairaConnect\PersistenceLayer\EntityReference;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class HistoryManager
{
    /**
     * @param EntityRepository<HistoryCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function getLastSentData(EntityReference $entityReference, SalesChannelContext $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addFilter(new EqualsFilter('entityName', $entityReference->getEntityName()));
        $criteria->addFilter(new EqualsFilter('entityId', $entityReference->getEntityId()));
        $criteria->addSorting(new FieldSorting('sentAt', FieldSorting::DESCENDING));
        $criteria->setLimit(1);

        /** @var HistoryEntity $entity */
        $entity = $this->repository->search($criteria, $context->getContext())->first();

        return $entity?->getData();
    }

    public function saveSentData(
        array $sentData,
        SalesChannelContext $context,
    ): void {
        $records = array_map(fn (array $record): array => array_merge($record, [
            'salesChannelId' => $context->getSalesChannelId(),
            'languageId' => $context->getLanguageId(),
        ]), $sentData);

        $this->repository->create($records, $context->getContext());
    }

    public function clear(SalesChannelContext $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));

        $ids = $this->repository->searchIds($criteria, $context->getContext())->getIds();

        if (0 < \count($ids)) {
            $this->repository->delete(array_map(fn (string $id): array => ['id' => $id], $ids), $context->getContext());
        }
    }

    public function garbageCollector(int $keep, SalesChannelContext $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salesChannelId', $context->getSalesChannelId()));
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addSorting(new FieldSorting('sentAt', FieldSorting::DESCENDING));

        $searchResult = $this->repository->search($criteria, $context->getContext());

        $grouped = [];

        /** @var HistoryEntity $item */
        foreach ($searchResult as $item) {
            $key = $item->getEntityName() . '|' . $item->getEntityId();

            if (!isset($grouped[$key])) {
                $grouped[$key] = [];
            }

            $grouped[$key][] = $item->getId();
        }

        foreach ($grouped as $group) {
            $delete = \array_slice($group, $keep);

            if (0 < \count($delete)) {
                $this->repository->delete(array_map(fn (string $id): array => ['id' => $id], $delete), $context->getContext());
            }
        }
    }
}
