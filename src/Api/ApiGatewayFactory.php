<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

use Ixomo\MakairaConnect\PluginConfig;
use Psr\Clock\ClockInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ApiGatewayFactory
{
    public function __construct(
        private readonly ApiClientFactory $apiClientFactory,
        private readonly ClockInterface $clock,
        private readonly PluginConfig $config,
    ) {
    }

    public function create(SalesChannelContext $context): ApiGatewayInterface
    {
        return new ApiGateway(
            $this->apiClientFactory->create($context),
            $this->clock,
            $this->config->getApiCustomer(),
            $this->config->getApiInstance()
        );
    }
}
