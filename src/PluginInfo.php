<?php

declare(strict_types=1);

namespace Makaira\Connect;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\PluginService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final readonly class PluginInfo
{
    private const MAKAIRA_PLUGIN_VERSION = '2.0.2';

    public function __construct(private string $shopwareVersion)
    {
    }

    public function getShopwareVersion(): string
    {
        return $this->shopwareVersion;
    }

    public function getPluginVersion(): string
    {
        return self::MAKAIRA_PLUGIN_VERSION;
    }
}
