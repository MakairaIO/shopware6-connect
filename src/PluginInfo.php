<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class PluginInfo
{
    public function __construct(private ParameterBagInterface $params, private PluginService $pluginService)
    {
    }

    public function getShopwareVersion(): string
    {
        return (string) $this->params->get('kernel.shopware_version');
    }

    public function getPluginVersion(): string
    {
        return $this->pluginService
            ->getPluginByName('IxomoMakairaConnect', Context::createDefaultContext())
            ->getVersion();
    }
}
