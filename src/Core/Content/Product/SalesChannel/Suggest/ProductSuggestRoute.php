<?php

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Suggest;

use AllowDynamicProperties;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
            throw new MissingRequestParameterException('search');
        }

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannel()->getId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        if (!Feature::isActive('v6.5.0.0')) {
            $context->getContext()->addState(Context::STATE_ELASTICSEARCH_AWARE);
        }

        $this->searchBuilder->build($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductSuggestCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SUGGEST_CRITERIA
        );

        $this->addElasticSearchContext($context);


        $query = $request->query->get('search');


        $client = new \GuzzleHttp\Client();

        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                "isSearch" => true,
                "enableAggregations" => true,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.language" => "at"
                ],
                "searchPhrase" => $query,
                "count" => "10"
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        $r =  json_decode($response->getBody()->getContents());

        $ids = [];
        foreach ($r->product->items as $product) {
            $ids[] = $product->id;
        }

        $newCriteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $newCriteria, $context)
        );

        $result = $this->productListingLoader->load($newCriteria, $context);

        $productMap = [];
        foreach ($result->getElements() as $element) {
            $productMap[$element->productNumber] = $element;
        }
        $result->clear();
        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $result->add($productMap[$id]);
            }
        }

        $result = ProductListingResult::createFrom($result);
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
