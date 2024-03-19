<?php

namespace Ixomo\MakairaConnect\Service;

use GuzzleHttp\Client;
use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

#[\AllowDynamicProperties]
class MakairaProductFetchingService
{
    private Client $client;
    private string $searchURL;
    private string $instance;

    public function __construct(private readonly PluginConfig $config)
    {
        $this->client = new Client();
        $this->searchURL = $this->config->getApiBaseUrl() . '/search/public';
        $this->instance = $this->config->getApiInstance();
    }

    public function fetchProductsFromMakaira(string $query, Criteria $criteria, array $makairaSorting, array $makairaFilter)
    {
        return $this->makeRequest([
            'isSearch' => true,
            'enableAggregations' => true,
            'aggregations' => $makairaFilter,
            'constraints' => $this->getDefaultConstraints(),
            'searchPhrase' => $query,
            'count' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'sorting' => $makairaSorting,
        ]);
    }

    public function fetchMakairaProductsFromCategory(string $categoryId, Criteria $criteria, array $filter, array $sorting)
    {
        return $this->makeRequest([
            'isSearch' => false,
            'enableAggregations' => true,
            'constraints' => array_merge($this->getDefaultConstraints(), ['query.category_id' => [$categoryId]]),
            'count' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'searchPhrase' => '',
            'aggregations' => $filter,
            'sorting' => $sorting,
            'customFilter' => [],
        ]);
    }

    public function fetchSuggestionsFromMakaira($query)
    {
        return $this->makeRequest([
            'isSearch' => true,
            'enableAggregations' => true,
            'constraints' => $this->getDefaultConstraints(),
            'searchPhrase' => $query,
            'count' => '10',
        ]);
    }

    private function makeRequest(array $payload): mixed
    {
        $response = $this->client->request('POST', $this->searchURL, [
            'json' => $payload,
            'headers' => ['X-Makaira-Instance' => $this->instance],
        ]);

        return $this->handleResponse($response);
    }

    private function getDefaultConstraints(): array
    {
        return [
            'query.shop_id' => '3',
            'query.use_stock' => true,
            'query.language' => 'at',
        ];
    }

    private function handleResponse($response): mixed
    {
        return json_decode((string) $response->getBody()->getContents(), false);
    }
}
