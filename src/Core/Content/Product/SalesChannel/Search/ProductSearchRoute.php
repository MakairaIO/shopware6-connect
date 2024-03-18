<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Search;

use Ixomo\MakairaConnect\Service\AggregationProcessingService;
use Ixomo\MakairaConnect\Service\FilterExtractionService;
use Ixomo\MakairaConnect\Service\MakairaProductFetchingService;
use Ixomo\MakairaConnect\Service\ShopwareProductFetchingService;
use Ixomo\MakairaConnect\Service\SortingMappingService;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSearchRoute extends AbstractProductSearchRoute
{
    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FilterExtractionService $filterExtractionService,
        private readonly SortingMappingService $sortingMappingService,
        private readonly ShopwareProductFetchingService $shopwareProductFetchingService,
        private readonly MakairaProductFetchingService $makairaProductFetchingService,
        private readonly AggregationProcessingService $aggregationProcessingService
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $this->validateSearchRequest($request);
        $query = $request->query->get('search');
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $makairaFilter = $this->filterExtractionService->extractMakairaFiltersFromRequest($request);

        $makairaSorting = $this->sortingMappingService->mapSortingCriteria($criteria);

        $makairaResponse = $this->makairaProductFetchingService->fetchProductsFromMakaira($query, $criteria, $makairaSorting, $makairaFilter);

        $shopwareResult = $this->shopwareProductFetchingService->fetchProductsFromShopware($makairaResponse,  $request,  $criteria,  $context);

        $result = $this->aggregationProcessingService->processAggregationsFromMakairaResponse($shopwareResult, $makairaResponse);
        $this->eventDispatcher->dispatch(new ProductSearchCriteriaEvent($request, $criteria, $context), ProductEvents::PRODUCT_SEARCH_CRITERIA);

        $finalResult = ProductListingResult::createFrom($result);
        $this->eventDispatcher->dispatch(new ProductSearchResultEvent($request, $finalResult, $context), ProductEvents::PRODUCT_SEARCH_RESULT);

        return new ProductSearchRouteResponse($finalResult);
    }

    private function validateSearchRequest(Request $request): void
    {
        if (!$request->get('search')) {
            throw RoutingException::missingRequestParameter('search');
        }
    }
}
