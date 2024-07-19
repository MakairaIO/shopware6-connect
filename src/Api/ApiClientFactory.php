<?php

declare(strict_types=1);

namespace Makaira\Connect\Api;

use Makaira\Connect\Exception\UnconfiguredException;
use Makaira\Connect\PluginConfig;
use Makaira\Connect\PluginInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiClientFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private PluginConfig $config,
        private PluginInfo $info,
    ) {
    }

    public function create(SalesChannelContext $context): ApiClient
    {
        $baseUrl = $this->config->getApiBaseUrl($context->getSalesChannelId());
        $userAgent = sprintf('Shopware/%s MakairaConnect/%s', $this->info->getShopwareVersion(), $this->info->getPluginVersion());
        $timeout = $this->config->getApiTimeout($context->getSalesChannelId());
        $sharedSecret = $this->config->getApiSharedSecret($context->getSalesChannelId());
        $instance = $this->config->getApiInstance($context->getSalesChannelId());

        if ('' === (string) $baseUrl || '' === (string) $sharedSecret || '' === (string) $instance) {
            throw UnconfiguredException::api($context->getSalesChannelId());
        }

        return new ApiClient($this->httpClient, new RequestSigner($sharedSecret), $baseUrl, $instance, $userAgent, $timeout);
    }
}
