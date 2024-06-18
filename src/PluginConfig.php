<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect;

use Shopware\Core\System\SystemConfig\SystemConfigService;

final readonly class PluginConfig
{
    private const KEY_PREFIX = 'IxomoMakairaConnect.config.';

    public function __construct(private SystemConfigService $systemConfigService)
    {
    }

    public function isConfigured(?string $salesChannelId = null): bool
    {
        return
            '' !== (string) $this->getApiBaseUrl($salesChannelId)
            && '' !== (string) $this->getApiSharedSecret($salesChannelId)
            && '' !== (string) $this->getApiCustomer($salesChannelId)
            && '' !== (string) $this->getApiInstance($salesChannelId)
            && '' !== (string) $this->getShopId($salesChannelId)
            && '' !== (string) $this->getLanguage($salesChannelId);
    }

    public function getApiBaseUrl(?string $salesChannelId = null): ?string
    {
        return $this->get('apiBaseUrl', $salesChannelId);
    }

    public function getApiTimeout(?string $salesChannelId = null): int
    {
        return $this->get('apiTimeout', $salesChannelId) ?? 30;
    }

    public function getApiSharedSecret(?string $salesChannelId = null): ?string
    {
        return $this->get('apiSharedSecret', $salesChannelId);
    }

    public function getApiCustomer(?string $salesChannelId = null): ?string
    {
        return $this->get('apiCustomer', $salesChannelId);
    }

    public function getApiInstance(?string $salesChannelId = null): ?string
    {
        return $this->get('apiInstance', $salesChannelId);
    }

    public function getShopId(?string $salesChannelId = null): ?string
    {
        return $this->get('shopId', $salesChannelId);
    }

    public function getLanguage(?string $salesChannelId = null): ?string
    {
        return $this->get('language', $salesChannelId);
    }

    public function getLastPersistenceLayerUpdate(?string $salesChannelId = null): ?\DateTimeInterface
    {
        $dateTime = $this->get('lastPersistenceLayerUpdate', $salesChannelId);

        if (null !== $dateTime && false !== ($parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dateTime))) {
            return $parsed;
        }

        return null;
    }

    public function setLastPersistenceLayerUpdate(?\DateTimeInterface $dateTime, ?string $salesChannelId = null): void
    {
        $this->set('lastPersistenceLayerUpdate', $dateTime?->format(\DateTimeInterface::ATOM), $salesChannelId);
    }

    private function get(string $key, ?string $salesChannelId = null): mixed
    {
        return $this->systemConfigService->get(self::KEY_PREFIX . $key, $salesChannelId);
    }

    private function set(string $key, mixed $value, ?string $salesChannelId = null): void
    {
        $this->systemConfigService->set(self::KEY_PREFIX . $key, $value, $salesChannelId);
    }
}
