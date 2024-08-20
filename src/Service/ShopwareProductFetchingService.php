<?php

declare(strict_types=1);

namespace Makaira\Connect\Service;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use stdClass;
use Symfony\Component\HttpFoundation\Request;

readonly class ShopwareProductFetchingService
{
    public function __construct(
        private SalesChannelRepository $salesChannelProductRepository,
        private RequestCriteriaBuilder $criteriaBuilder,
        private ProductDefinition $definition,
    ) {
    }

    public function fetchProductsFromShopware(
        stdClass $makairaResponse,
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context,
    ): EntitySearchResult {
        $ids = $this->extractProductIdsFromMakairaResponse($makairaResponse);

        $newCriteria = $this->buildNewCriteriaFromRequestAndMakairaResponse($request, $ids, $context, $criteria);

        $shopwareResult = $this->searchShopwareProducts($newCriteria, $context);
        // Restore original pagination
        $shopwareResult->getCriteria()->setOffset($criteria->getOffset());
        $shopwareResult->getCriteria()->setLimit($criteria->getLimit());
        $shopwareResult->setLimit($criteria->getLimit());

        return $this->reorderProductsAccordingToMakairaIds($shopwareResult, $ids, $makairaResponse->product->total);
    }

    private function buildNewCriteriaFromRequestAndMakairaResponse(Request $request, array $ids, SalesChannelContext $context, Criteria $originalCriteria): Criteria
    {
        $newCriteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $this->definition, $context->getContext());
        if ($originalCriteria->getSorting()) {
            $newCriteria->addSorting($originalCriteria->getSorting()[0]);
        }
        if (isset($originalCriteria->getExtensions()['aggregations'])) {
            $newCriteria->addExtension('aggregations', $originalCriteria->getExtensions()['aggregations']);
        }
        if (isset($originalCriteria->getExtensions()['sortings'])) {
            $newCriteria->addExtension('sortings', $originalCriteria->getExtensions()['sortings']);
        }
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));
        $newCriteria->setOffset(0);
        $newCriteria->setLimit($originalCriteria->getLimit());

        return $newCriteria;
    }

    private function extractProductIdsFromMakairaResponse(stdClass $makairaResponse): array
    {
        return array_map(static fn ($product) => $product->id, $makairaResponse->product->items);
    }

    private function searchShopwareProducts(Criteria $newCriteria, SalesChannelContext $context): EntitySearchResult
    {
        return $this->salesChannelProductRepository->search($newCriteria, $context);
    }

    private function reorderProductsAccordingToMakairaIds(EntitySearchResult $shopwareResult, array $ids, int $total): EntitySearchResult
    {
        $productMap = array_column($shopwareResult->getEntities()->getElements(), null, 'productNumber');

        $orderedProducts = array_filter(array_map(static fn ($id) => $productMap[$id] ?? null, $ids));

        return new EntitySearchResult('product', $total, new EntityCollection($orderedProducts), $shopwareResult->getAggregations(), $shopwareResult->getCriteria(), $shopwareResult->getContext());
    }
}
