<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Listing;

use Ixomo\MakairaConnect\Service\AggregationProcessingService;
use Ixomo\MakairaConnect\Service\FilterExtractionService;
use Ixomo\MakairaConnect\Service\MakairaProductFetchingService;
use Ixomo\MakairaConnect\Service\ShopwareProductFetchingService;
use Ixomo\MakairaConnect\Service\SortingMappingService;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[\AllowDynamicProperties]
class ProductListingRoute extends AbstractProductListingRoute
{
    public function __construct(
        AbstractProductListingRoute $decorated,
        EntityRepositoryInterface $categoryRepository,
        ProductStreamBuilderInterface $productStreamBuilder,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepository $salesChannelProductRepository,
        private readonly FilterExtractionService $filterExtractionService,
        private readonly SortingMappingService $sortingMappingService,
        private readonly MakairaProductFetchingService $makairaProductFetchingService,
        private readonly ShopwareProductFetchingService $shopwareProductFetchingService,
        private readonly AggregationProcessingService $aggregationProcessingService
    ) {
        $this->decorated = $decorated;
        $this->categoryRepository = $categoryRepository;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria,
    ): ProductListingRouteResponse {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $makairaFilter = $this->filterExtractionService->extractMakairaFiltersFromRequest($request);

        $category = $this->fetchCategory($categoryId, $context);
        if (isset($category->getCustomFields()['loberonCatId'])){
            $catId = $category->getCustomFields()['loberonCatId'];
        } else {
            return $this->decorated->load($categoryId, $request, $context, $criteria);
        }
        $streamId = $this->extendCriteria($context, $criteria, $category);

        $makairaSorting = $this->sortingMappingService->mapSortingCriteria($criteria);

        $makairaResponse = $this->makairaProductFetchingService->fetchMakairaProductsFromCategory($catId, $criteria, $makairaFilter, $makairaSorting);

        $shopwareResult = $this->shopwareProductFetchingService->fetchProductsFromShopware($makairaResponse,  $request,  $criteria,  $context);

        $result = $this->aggregationProcessingService->processAggregationsFromMakairaResponse($shopwareResult, $makairaResponse);


        /** @var ProductListingResult $result */
        $finalResult = ProductListingResult::createFrom($result);

        $finalResult->addCurrentFilter('navigationId', $categoryId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $finalResult, $context)
        );

        $finalResult->setStreamId($streamId);

        return new ProductListingRouteResponse($finalResult);
    }

    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, CategoryEntity $category): ?string
    {
        if (CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM === $category->getProductAssignmentType() && null !== $category->getProductStreamId()) {
            $filters = $this->productStreamBuilder->buildFilters(
                $category->getProductStreamId(),
                $salesChannelContext->getContext()
            );

            $criteria->addFilter(...$filters);

            return $category->getProductStreamId();
        }

        $criteria->addFilter(
            new EqualsFilter('product.categoriesRo.id', $category->getId())
        );

        return null;
    }

    private function fetchCategory(string $categoryId, SalesChannelContext $context): CategoryEntity
    {
        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('product-listing-route::category-loading');
        /** @var CategoryEntity $category */
        return $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();
    }
}
