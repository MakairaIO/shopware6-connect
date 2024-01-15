<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

use Ixomo\MakairaConnect\Exception\UnconfiguredException;
use Ixomo\MakairaConnect\PluginConfig;
use Ixomo\MakairaConnect\PluginInfo;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiClientFactory
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly PluginConfig $config,
        private readonly PluginInfo $info,
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
