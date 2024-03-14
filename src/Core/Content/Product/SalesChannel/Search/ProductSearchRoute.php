<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Core\Content\Product\SalesChannel\Search;

use DreiscSeoPro\Core\Content\Product\ProductRepository;
use Ixomo\MakairaConnect\Extension\Content\Product\CustomExtension;
use Shopware\Core\Content\Product\Events\ProductSearchCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Routing\RoutingException;
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



    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {

        if (!$request->get('search')) {
            throw RoutingException::missingRequestParameter('search');
        }

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $query = $request->query->get('search');



        $makairaFilter = [];
        foreach ($request->query as $key => $value) {
            if (str_starts_with($key, 'filter_')) {
                $makairaFilter[str_replace("filter_", "", $key)] = explode('|', $value);
            }
        }

        $min = $request->query->get('min-price');
        $max = $request->query->get('max-price');

        if ($min) {
            $makairaFilter['price_from'] = $min;
        }

        if ($max) {
            $makairaFilter['price_to'] = $max;
        }


        if (in_array('nachhaltigkeit', $makairaFilter)) {
            $makairaFilter['nachhaltigkeit'] = 1;
        }

        if (in_array('sale', $makairaFilter)) {
            $makairaFilter['Sale'] = 1;
        }



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
                "aggregations" => $makairaFilter,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.use_stock" => true,
                    "query.language" => "at"
                ],
                "searchPhrase" => $query,
                "count" => $count,
                "offset" => $offset,
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
        $newCriteria->addExtension('sortings', $criteria->getExtensions()['sortings']);
        $aggs = $criteria->getAggregations();
        $newCriteria->addAggregation(...$aggs);
        $newCriteria->addFilter(new EqualsAnyFilter('productNumber', $ids));

        $newCriteria->setOffset(0);
        $result = $this->salesChannelProductRepository->search($newCriteria,  $context);
        // back to original offset, so pagination in shopware works
        $result->getCriteria()->setOffset($offset);
        $result->getCriteria()->setLimit($count);


        foreach ($r->product->aggregations as $aggregation) {
            if ($aggregation->type == 'list_multiselect') {
                $makFilter = new PropertyGroupEntity();
                $makFilter->setName($aggregation->key);
                $makFilter->setId($aggregation->key);
                $makFilter->setDisplayType('color');
                $makFilter->setTranslated(['name' => $aggregation->title, 'position' => $aggregation->position]);
                $makFilter->setFilterable(true);

                $options = [];

                foreach ($aggregation->values as $value) {
                    $color = new PropertyGroupOptionEntity();
                    $color->setName($value->key);
                    $color->setId($value->key);
                    $color->setColorHexCode($this->getColorName($value->key));
                    $color->setTranslated(['name' => $this->getColorLocalizedName($value->key), 'position' => $value->position]);

                    $options[] = $color;
                }

                $makFilter->setOptions(
                    new PropertyGroupOptionCollection(
                        $options
                    )
                );

                $result->getAggregations()->add(
                    new EntityResult(
                        'filter_' . $aggregation->key,
                        new PropertyGroupCollection([$makFilter])
                    )
                );
            } elseif ($aggregation->type == 'range_slider_price') {

                $makFilter = new StatsResult(
                    'filter_' . $aggregation->key,
                    $aggregation->min,
                    $aggregation->max,
                    ($aggregation->min + $aggregation->max) / 2,
                    $aggregation->max
                );

                $result->getAggregations()->add(
                    $makFilter
                );
            } elseif ($aggregation->type == 'list_multiselect_custom_1') {

                $options = [];

                $color = new PropertyGroupOptionEntity();
                $color->setName($aggregation->key);
                $color->setId($aggregation->key);
                $color->setTranslated(['name' => $aggregation->title]);
                $options[] = $color;

                $makFilter = new EntityResult('filter_' . $aggregation->key, new EntityCollection(
                    $options
                ));


                $result->getAggregations()->add(
                    $makFilter
                );
            }
        }



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
            new ProductSearchCriteriaEvent($request, $criteria, $context),
            ProductEvents::PRODUCT_SEARCH_CRITERIA
        );

        $result = ProductListingResult::createFrom($newResult);
        $this->eventDispatcher->dispatch(
            new ProductSearchResultEvent($request, $result, $context),
            ProductEvents::PRODUCT_SEARCH_RESULT
        );



        return new ProductSearchRouteResponse($result);
    }

    private function getColorName($key)
    {
        $colorIds = [
            1  => 'black',
            2  => 'brown',
            3  => 'beige',
            4  => 'gray',
            5  => 'white',
            6  => 'blue',
            7  => 'petrol',
            8  => 'green',
            9  => 'yellow',
            10 => 'orange',
            11 => 'red',
            12 => 'pink',
            13 => 'purple',
            14 => 'gold',
            15 => 'silver',
            16 => 'bronze',
            17 => 'champagner',
            18 => 'brass',
            19 => 'clear',
            20 => 'colorful',
            21 => 'colorful',
            22 => 'creme',
            23 => 'creme',
            24 => 'taupe',
            25 => 'copper',
            26 => 'linen',
            30 => 'red',
            31 => 'green',
            32 => 'pink',
            33 => 'green',
        ];
        return $colorIds[min($key, count($colorIds) - 1)];
    }


    private function getColorLocalizedName($key)
    {
        $localizedColorNames = [
            1  => 'Schwarz',
            2  => 'Braun',
            3  => 'Beige',
            4  => 'Grau',
            5  => 'Weiß',
            6  => 'Blau',
            7  => 'Türkis',
            8  => 'Grün',
            9  => 'Gelb',
            10 => 'Orange',
            11 => 'Rot',
            12 => 'Pink',
            13 => 'Lila',
            14 => 'Gold',
            15 => 'Silber',
            16 => 'Bronze',
            17 => 'Champagner',
            18 => 'Messing',
            19 => 'Klar',
            20 => 'Bunt',
            21 => 'Gemustert',
            22 => 'Creme',
            23 => 'Natur',
            24 => 'Taupe',
            25 => 'Kupfer',
            26 => 'Leinen',
            30 => 'Dunkelrot',
            31 => 'Hellgrün',
            32 => 'Dunkelrosa',
            33 => 'Dunkelgrün',
        ];

        return $localizedColorNames[min($key, count($localizedColorNames) - 1)];
    }
}
