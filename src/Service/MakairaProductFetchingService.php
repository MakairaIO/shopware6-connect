<?php

namespace Ixomo\MakairaConnect\Service;

use GuzzleHttp\Client;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class MakairaProductFetchingService
{

    public function fetchProductsFromMakaira(string $query, Criteria $criteria, array $makairaSorting, array $makairaFilter)
    {
        $client = new Client();
        $count = $criteria->getLimit();
        $offset = $criteria->getOffset();
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                'isSearch' => true,
                'enableAggregations' => true,
                'aggregations' => $makairaFilter,
                'constraints' => [
                    'query.shop_id' => '3',
                    'query.use_stock' => true,
                    'query.language' => 'at',
                ],
                'searchPhrase' => $query,
                'count' => $count,
                'offset' => $offset,
                'sorting' => $makairaSorting,
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        return json_decode((string) $response->getBody()->getContents());
    }

    public function fetchMakairaProductsFromCategory(
        string $categoryId,
        Criteria $criteria,
        array $filter,
        array $sorting,
    ) {
        $count = $criteria->getLimit();
        $offset = $criteria->getOffset();
        $client = new Client();
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                'isSearch' => false,
                'enableAggregations' => true,
                'constraints' => [
                    'query.shop_id' => '3',
                    'query.language' => 'at',
                    'query.use_stock' => true,
                    'query.category_id' => [$categoryId],
                ],
                'count' => $count,
                'offset' => $offset,
                'searchPhrase' => '',
                'aggregations' => $filter,
                'sorting' => $sorting,
                'customFilter' => [],
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        return json_decode((string) $response->getBody()->getContents());
    }

    public function fetchSuggestionsFromMakaira($query)
    {
        $client = new Client();
        $response = $client->request('POST', 'https://loberon.makaira.io/search/public', [
            'json' => [
                'isSearch' => true,
                'enableAggregations' => true,
                'constraints' => [
                    'query.shop_id' => '3',
                    'query.use_stock' => true,
                    'query.language' => 'at',
                ],
                'searchPhrase' => $query,
                'count' => '10',
            ],
            'headers' => [
                'X-Makaira-Instance' => 'live_at_sw6',
            ],
        ]);

        return json_decode((string) $response->getBody()->getContents());
    }
}
