<?php

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Listing;

use AllowDynamicProperties;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AllowDynamicProperties]
class ProductListingRoute extends AbstractProductListingRoute
{

    public function __construct(
        AbstractProductListingRoute $decorated,
        EntityRepositoryInterface $categoryRepository,
        ProductListingLoader $listingLoader,
        ProductStreamBuilderInterface $productStreamBuilder,
        EventDispatcherInterface $eventDispatcher,
        RequestCriteriaBuilder $criteriaBuilder,
        SalesChannelRepository $salesChannelProductRepository,
        ProductDefinition $definition,


    ) {
        $this->decorated = $decorated;
        $this->categoryRepository = $categoryRepository;
        $this->listingLoader = $listingLoader;
        $this->productStreamBuilder = $productStreamBuilder;
        $this->eventDispatcher = $eventDispatcher;
        $this->criteriaBuilder = $criteriaBuilder;
        $this->salesChannelProductRepository = $salesChannelProductRepository;
        $this->definition = $definition;
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(
        string $categoryId,
        Request $request,
        SalesChannelContext $context,
        Criteria $criteria
    ): ProductListingRouteResponse {
        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_ALL)
        );
        $criteria->setTitle('product-listing-route::loading');

        $categoryCriteria = new Criteria([$categoryId]);
        $categoryCriteria->setTitle('product-listing-route::category-loading');

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();
        $catId = $category->getCustomFields()['loberonCatId'];
        $streamId = $this->extendCriteria($context, $criteria, $category);

        $count = $criteria->getLimit();
        $offset = $criteria->getOffset();

        $response = $this->fetchMakairaProductsFromCategory($catId, $count, $offset);
        $r =  json_decode($response->getBody()->getContents());
        $total = $r->product->total;
        $products = $r->product->items;
        $ids = [];
        foreach ($products as $product) {
            $ids[] = $product->id;
        }


        $newCriteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));

        $newCriteria->setOffset(0);
        $result = $this->salesChannelProductRepository->search($newCriteria,  $context);

        $result->getCriteria()->setOffset($offset);
        $result->getCriteria()->setLimit($count);

        $newResult = new EntitySearchResult(
            'product',
            $total,
            $result->getEntities(),
            $result->getAggregations(),
            $criteria,
            $result->getContext()
        );
        $productMap = [];
        foreach ($newResult->getElements() as $element) {
            $productMap[$element->productNumber] = $element;
        }
        $result->clear();
        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $newResult->add($productMap[$id]);
            }
        }

        /** @var ProductListingResult $result */
        $result = ProductListingResult::createFrom($newResult);

        $result->addCurrentFilter('navigationId', $categoryId);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $result, $context)
        );

        $result->setStreamId($streamId);

        return new ProductListingRouteResponse($result);
    }

    private function extendCriteria(SalesChannelContext $salesChannelContext, Criteria $criteria, CategoryEntity $category): ?string
    {
        if ($category->getProductAssignmentType() === CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM && $category->getProductStreamId() !== null) {
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

    public function fetchMakairaProductsFromCategory(
        string $categoryId,
        int $count,
        int $offset
    )
    {
        $client = new \GuzzleHttp\Client();
        // TODO: pagination, so count and offset?
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                "isSearch" => false,
                "enableAggregations" => true,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.language" => "at",
                    "query_use_stock" => true,
                    "query.category_id" => [$categoryId],
                ],
                "count" => $count,
                "offset" => $offset,
                "searchPhrase" => "",
                "aggregations" => [],
                "sorting" => [],
                "customFilter" => [],
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        return $response;
    }
}
