<?php

declare(strict_types=1);

namespace Makaira\Connect\Core\Content\Product\SalesChannel\Suggest;

use AllowDynamicProperties;
use Makaira\Connect\Service\MakairaProductFetchingService;
use Makaira\Connect\Service\ShopwareProductFetchingService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

#[AllowDynamicProperties]
class ProductSuggestRoute extends AbstractProductSuggestRoute
{
    public function __construct(
        AbstractProductSuggestRoute $decorated,
        ProductSearchBuilderInterface $searchBuilder,
        EventDispatcherInterface $eventDispatcher,
        ProductListingLoader $productListingLoader,
        RequestCriteriaBuilder $criteriaBuilder,
        SalesChannelRepository $salesChannelProductRepository,
        ProductDefinition $definition,
        private readonly MakairaProductFetchingService $makairaProductFetchingService,
        private readonly ShopwareProductFetchingService $shopwareProductFetchingService,
        private readonly LoggerInterface $httpClientLogger,
    ) {
        $this->decorated = $decorated;
        $this->eventDispatcher = $eventDispatcher;
        $this->searchBuilder = $searchBuilder;
        $this->productListingLoader = $productListingLoader;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
        $this->definition = $definition;
    }

    public function getDecorated(): AbstractProductSuggestRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSuggestRouteResponse
    {
        if (!$request->get('search')) {
            throw RoutingException::missingRequestParameter('search');
        }
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        if (!Feature::isActive('v6.5.0.0')) {
            $context->getContext()->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        }
        $this->searchBuilder->build($request, $criteria, $context);
        $this->eventDispatcher->dispatch(
            new ProductSuggestCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SUGGEST_CRITERIA
        );
        $this->addElasticSearchContext($context);

        $query = $request->query->get('search');

        try {
            $makairaResponse = $this->makairaProductFetchingService->fetchSuggestionsFromMakaira($context, $query);
        } catch (Throwable $exception) {
            $this->httpClientLogger->error('[Makaira] ' . $exception->getMessage(), ['type' => self::class]);

            return $this->decorated->load($request, $context, $criteria);
        }

        $shopwareResult = $this->shopwareProductFetchingService->fetchProductsFromShopware($makairaResponse, $request, $criteria, $context);

        $result = ProductListingResult::createFrom($shopwareResult);

        $categories = $makairaResponse->category->items ?? [];
        $categoriesEntity = new ArrayEntity(array_splice($categories, 0, 10));
        $result->addExtension('makairaCategories', $categoriesEntity);

        $this->eventDispatcher->dispatch(
            new ProductSuggestResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SUGGEST_RESULT
        );

        return new ProductSuggestRouteResponse($result);
    }

    public function addElasticSearchContext(SalesChannelContext $context): void
    {
        $context->getContext()->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
    }
}
