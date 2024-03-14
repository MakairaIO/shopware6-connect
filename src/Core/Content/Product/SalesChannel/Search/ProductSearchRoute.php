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
    const MAKAIRA_SORTING_MAPPING = [
        'field' => [
            'product.name' => 'title',
            'product.cheapestPrice' => 'price',
        ],
        'direction' => [
            'ASC' => 'asc',
            'DESC' => 'desc',
        ],
    ];

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
        $sorting = $criteria->getSorting();
        // map sorting from criteria to makaira
        $sort = [];
        foreach ($sorting as $sortingField) {
            $field = self::MAKAIRA_SORTING_MAPPING['field'][$sortingField->getField()] ?? null;
            $direction = self::MAKAIRA_SORTING_MAPPING['direction'][$sortingField->getDirection()] ?? null;
            if ($field && $direction) {
                $sort[] = [$field, $direction];
            }
        }
        $makairaSorting = $sort ? [$sort[0][0] => $sort[0][1]] : [];
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                "isSearch" => true,
                "enableAggregations" => true,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.use_stock" => true,
                    "query.language" => "at"
                ],
                "searchPhrase" => $query,
                "count" => $count,
                "offset"=> $offset,
                "sorting" => $makairaSorting
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

        $newCriteria = $this->criteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->definition,
            $context->getContext()
        );

        $newCriteria->addSorting($criteria->getSorting()[0]);
        $newCriteria->addExtension('sortings',$criteria->getExtensions()['sortings']);
        $aggs = $criteria->getAggregations();
        $newCriteria->addAggregation(...$aggs);
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));

        $newCriteria->setOffset(0);
        $result = $this->salesChannelProductRepository->search($newCriteria,  $context);
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
