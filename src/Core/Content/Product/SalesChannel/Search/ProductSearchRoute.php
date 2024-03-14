<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Search;

use DreiscSeoPro\Core\Content\Product\ProductRepository;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductSearchRoute extends AbstractProductSearchRoute
{

    public function __construct(
        private readonly AbstractProductSearchRoute $decorated,
        private readonly ProductSearchBuilderInterface $searchBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SalesChannelRepository $salesChannelProductRepository,
        private readonly ProductDefinition $definition,
        private readonly RequestCriteriaBuilder $criteriaBuilder,
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        return $this->decorated;
    }

    public function load(
        Request $request,
        SalesChannelContext $context,
        ?Criteria $criteria = null
    ): ProductSearchRouteResponse {

        $this->addElasticSearchContext($context);


        $query = $request->query->get('search');


        $client = new \GuzzleHttp\Client();
        $count = $criteria->getLimit();
        $offset = $criteria->getOffset();
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                "isSearch" => true,
                "enableAggregations" => true,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.language" => "at"
                ],
                "searchPhrase" => $query,
                "count" => $count,
                "offset"=> $offset,
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        $r =  json_decode($response->getBody()->getContents());
        $total = $r->product->total;

        $ids = [];
        foreach ($r->product->items as $product) {
             $ids[] = $product->id;
         }

        $criteria ??= $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );

        $criteria->addFilter(new EqualsAnyFilter('productNumber', $ids));
        $criteria->resetSorting();
        // we have to set offset on 0, because we only have 24 product
        $criteria->setOffset(0);
        $result = $this->salesChannelProductRepository->search($criteria,  $context);
        // back to original offset, so pagination in shopware works
        $result->getCriteria()->setOffset($offset);
        $newResult = new EntitySearchResult(
            'product',
            $total,
            $result->getEntities(),
            $result->getAggregations(),
            $result->getCriteria(),
            $result->getContext()
        );

        //sort result elements by productNumber from $ids
        $productMap = [];
        foreach ($newResult->getElements() as $element) {
            $productMap[$element->productNumber] = $element;
        }
        $newResult->clear();
        // Step 2: Reorder products based on $ids
        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $newResult->add($productMap[$id]);
            }
        }

        $this->eventDispatcher->dispatch(
            new ProductSearchCriteriaEvent($request, $criteria, $context)
        );

        $result = ProductListingResult::createFrom($newResult);
        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context)
        );

        return new ProductSearchRouteResponse($result);
    }

    public function addElasticSearchContext(SalesChannelContext $context): void
    {
        $context->getContext()->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
    }
}
