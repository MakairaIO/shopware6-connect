<?php

declare(strict_types=1);

namespace Makaira\Connect\Api;

use Makaira\Connect\PluginConfig;
use Psr\Clock\ClockInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class ApiGatewayFactory
{
    public function __construct(
        private ApiClientFactory $apiClientFactory,
        private ClockInterface $clock,
        private PluginConfig $config,
    ) {
    }

    public function create(SalesChannelContext $context): ApiGatewayInterface
    {
        return new ApiGateway(
            $this->apiClientFactory->create($context),
            $this->clock,
            $this->config->getApiCustomer($context->getSalesChannelId()),
            $this->config->getApiInstance($context->getSalesChannelId())
        );
    }
}
