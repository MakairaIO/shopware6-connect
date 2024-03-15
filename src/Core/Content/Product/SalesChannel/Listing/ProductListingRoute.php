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
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
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
    public const MAKAIRA_SORTING_MAPPING = [
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


        $makairaFilter = [];
        foreach ($request->query as $key => $value) {
            if (str_starts_with((string) $key, 'filter_')) {
                $makairaFilter[str_replace("filter_", "", (string) $key)] = explode('|', (string) $value);
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


        if (array_key_exists('nachhaltigkeit', $makairaFilter)) {
            unset($makairaFilter['nachhaltigkeit']);
            $makairaFilter['Nachhaltigkeit'] = [1];
        }

        if (array_key_exists('sale', $makairaFilter)) {
            unset($makairaFilter['sale']);
            $makairaFilter['Sale'] = [1];
        }



        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search($categoryCriteria, $context->getContext())->first();
        $catId = $category->getCustomFields()['loberonCatId'];
        $streamId = $this->extendCriteria($context, $criteria, $category);

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
        $response = $this->fetchMakairaProductsFromCategory($catId, $count, $offset, $makairaFilter, $makairaSorting);
        $r =  json_decode((string) $response->getBody()->getContents());
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
            $criteria,
            $result->getContext()
        );


        $productMap = [];
        foreach ($newResult->getElements() as $element) {
            $productMap[$element->productNumber] = $element;
        }
        $newResult->clear();
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
        int $offset,
        array $filter,
        array $sorting
    ) {
        $client = new \GuzzleHttp\Client();
        // TODO: pagination, so count and offset?
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                "isSearch" => false,
                "enableAggregations" => true,
                "constraints" => [
                    "query.shop_id" => "3",
                    "query.language" => "at",
                    "query.use_stock" => true,
                    "query.category_id" => [$categoryId],
                ],
                "count" => $count,
                "offset" => $offset,
                "searchPhrase" => "",
                "aggregations" => $filter,
                "sorting" => $sorting,
                "customFilter" => [],
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        return $response;
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
