<?php

namespace Ixomo\MakairaConnect\Service;

use GuzzleHttp\Client;
use Ixomo\MakairaConnect\Api\ApiClient;
use Ixomo\MakairaConnect\Api\ApiClientFactory;
use Ixomo\MakairaConnect\Events\ModifierQueryRequestEvent;
use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[\AllowDynamicProperties]
class MakairaProductFetchingService
{
    private ApiClient $client;

    public function __construct(
        private readonly PluginConfig $config,
        private readonly ApiClientFactory $apiClientFactory,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function fetchProductsFromMakaira(SalesChannelContext $context, string $query, Criteria $criteria, array $makairaSorting, array $makairaFilter): ?\stdClass
    {
        return $this->makeRequest($context, $this->dispatchEvent(
            ModifierQueryRequestEvent::NAME_SEARCH,
            [
                'isSearch' => true,
                'enableAggregations' => true,
                'aggregations' => $makairaFilter,
                'constraints' => $this->getDefaultConstraints($context),
                'searchPhrase' => $query,
                'count' => $criteria->getLimit(),
                'offset' => $criteria->getOffset(),
                'sorting' => $makairaSorting,
            ])
        );
    }

    public function fetchMakairaProductsFromCategory(SalesChannelContext $context, string $categoryId, Criteria $criteria, array $filter, array $sorting): ?\stdClass
    {
        return $this->makeRequest($context, $this->dispatchEvent(
            ModifierQueryRequestEvent::NAME_SEARCH,
            [
                'isSearch' => false,
                'enableAggregations' => true,
                'constraints' => array_merge($this->getDefaultConstraints($context), ['query.category_id' => [$categoryId]]),
                'count' => $criteria->getLimit(),
                'offset' => $criteria->getOffset(),
                'searchPhrase' => '',
                'aggregations' => $filter,
                'sorting' => $sorting,
                'customFilter' => [],
            ])
        );
    }

    public function fetchSuggestionsFromMakaira(SalesChannelContext $context, $query): ?\stdClass
    {
        return $this->makeRequest($context, [
            'isSearch' => true,
            'enableAggregations' => true,
            'constraints' => $this->getDefaultConstraints($context),
            'searchPhrase' => $query,
            'count' => '10',
        ]);
    }

    private function makeRequest(SalesChannelContext $context, array $payload): ?\stdClass
    {
        $searchURL = $this->config->getApiBaseUrl($context->getSalesChannelId()) . '/search/';

        /** loberon */
        lbLoggerBrowser()->notice('Makaira request: ' . $payload['searchPhrase'] ?? '', compact('searchURL', 'payload'));
        /** end loberon */

        $http = $this->getClient($context)->request(method: 'POST', url: $searchURL, data: $payload);
        $handleResponse = $this->handleResponse($http);

        /** loberon */
        $row = $http->getContent();
        lbLoggerBrowser()->notice('Makaira response', compact('row', 'handleResponse'));
        /** end loberon */

        return $handleResponse;
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

    private function handleResponse(ResponseInterface $response): ?\stdClass
    {
        return json_decode((string) $response->getContent(), false);
    }

    private function dispatchEvent($eventName, array $query): array
    {
        $event = new ModifierQueryRequestEvent($query);
        $this->dispatcher->dispatch(
            event: $event,
            eventName: $eventName
        );

        return $event->getQuery()->getArrayCopy();
    }

    private function getClient(SalesChannelContext $context): ApiClient
    {
        return $this->client ??= $this->apiClientFactory->create($context);
    }
}
