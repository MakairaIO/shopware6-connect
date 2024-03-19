<?php

namespace Ixomo\MakairaConnect\Service;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ShopwareProductFetchingService
{
    public function __construct(
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
        private readonly ProductDefinition $definition
    ) {
    }
    public function fetchProductsFromShopware(
        $makairaResponse,
        Request $request,
        Criteria $criteria,
        SalesChannelContext $context
    ): EntitySearchResult
    {
        $ids = $this->extractProductIdsFromMakairaResponse($makairaResponse);

        $newCriteria = $this->buildNewCriteriaFromRequestAndMakairaResponse($request, $ids, $context, $criteria);

        $shopwareResult = $this->searchShopwareProducts($newCriteria, $context);

        // Restore original pagination
        $shopwareResult->getCriteria()->setOffset($criteria->getOffset());
        $shopwareResult->getCriteria()->setLimit($criteria->getLimit());

        $shopwareResult = $this->reorderProductsAccordingToMakairaIds($shopwareResult, $ids, $makairaResponse->product->total);
        return $shopwareResult;
    }

    private function buildNewCriteriaFromRequestAndMakairaResponse(Request $request, array $ids, SalesChannelContext $context, Criteria $originalCriteria): Criteria
    {
        $newCriteria = $this->criteriaBuilder->handleRequest($request, new Criteria(), $this->definition, $context->getContext());
        if ($originalCriteria->getSorting()) {
            $newCriteria->addSorting($originalCriteria->getSorting()[0]);
        }
        if (isset($originalCriteria->getExtensions()['aggregations'])){
            $newCriteria->addExtension('aggregations', $originalCriteria->getExtensions()['aggregations']);
        }
        if (isset($originalCriteria->getExtensions()['sortings'])){
            $newCriteria->addExtension('sortings', $originalCriteria->getExtensions()['sortings']);
        }
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));
        $newCriteria->setOffset(0);

        return $newCriteria;
    }

    private function extractProductIdsFromMakairaResponse($makairaResponse): array
    {
        return array_map(fn($product) => $product->id, $makairaResponse->product->items);
    }

    private function searchShopwareProducts(Criteria $newCriteria, SalesChannelContext $context): EntitySearchResult
    {
        return $this->salesChannelProductRepository->search($newCriteria, $context);
    }

    private function reorderProductsAccordingToMakairaIds(EntitySearchResult $shopwareResult, array $ids, int $total): EntitySearchResult
    {
        $productMap = array_column($shopwareResult->getEntities()->getElements(), null, 'productNumber');
        $orderedProducts = array_intersect_key($productMap, array_flip($ids));
        return new EntitySearchResult('product', $total, new EntityCollection($orderedProducts), $shopwareResult->getAggregations(), $shopwareResult->getCriteria(), $shopwareResult->getContext());
    }
}
