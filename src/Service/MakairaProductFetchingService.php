<?php

namespace Ixomo\MakairaConnect\Service;

use GuzzleHttp\Client;
use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[\AllowDynamicProperties]
class MakairaProductFetchingService
{
    private Client $client;


    public function __construct(private readonly PluginConfig $config)
    {
        $this->client = new Client();
    }

    public function fetchProductsFromMakaira(SalesChannelContext $context, string $query, Criteria $criteria, array $makairaSorting, array $makairaFilter)
    {
        return $this->makeRequest($context, [
            'isSearch' => true,
            'enableAggregations' => true,
            'aggregations' => $makairaFilter,
            'constraints' => $this->getDefaultConstraints($context),
            'searchPhrase' => $query,
            'count' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'sorting' => $makairaSorting,
        ]);
    }

    public function fetchMakairaProductsFromCategory(SalesChannelContext $context, string $categoryId, Criteria $criteria, array $filter, array $sorting)
    {
        return $this->makeRequest($context, [
            'isSearch' => false,
            'enableAggregations' => true,
            'constraints' => array_merge($this->getDefaultConstraints($context), ['query.category_id' => [$categoryId]]),
            'count' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'searchPhrase' => '',
            'aggregations' => $filter,
            'sorting' => $sorting,
            'customFilter' => [],
        ]);
    }

    public function fetchSuggestionsFromMakaira(SalesChannelContext $context, $query)
    {
        return $this->makeRequest($context, [
            'isSearch' => true,
            'enableAggregations' => true,
            'constraints' => $this->getDefaultConstraints($context),
            'searchPhrase' => $query,
            'count' => '10',
        ]);
    }

    private function makeRequest(SalesChannelContext $context, array $payload): mixed
    {

        $searchURL = $this->config->getApiBaseUrl($context->getSalesChannelId()) . '/search/public';
        $instance = $this->config->getApiInstance($context->getSalesChannelId());


        $response = $this->client->request('POST', $searchURL, [
            'json' => $payload,
            'headers' => ['X-Makaira-Instance' => $instance],
        ]);

        return $this->handleResponse($response);
    }

    private function getDefaultConstraints(SalesChannelContext $context): array
    {
        $language = $this->config->getLanguage($context->getSalesChannelId());
        $shopId = $this->config->getShopId($context->getSalesChannelId());

        return [
            'query.shop_id' => $shopId,
            'query.use_stock' => true,
            'query.language' => $language,
        ];
    }

    private function handleResponse($response): mixed
    {
        return json_decode((string) $response->getBody()->getContents(), false);
    }
}
