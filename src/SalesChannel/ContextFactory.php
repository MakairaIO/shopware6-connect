<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\SalesChannel;

use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

final readonly class ContextFactory
{
    /**
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private EntityRepository $salesChannelRepository,
        private AbstractSalesChannelContextFactory $contextFactory,
        private PluginConfig $config,
    ) {
    }

    public function create(string $salesChannelId, ?string $languageId = null): SalesChannelContext
    {
        return $this->contextFactory->create(Uuid::randomHex(), $salesChannelId, [
            SalesChannelContextService::LANGUAGE_ID => $languageId,
        ]);
    }

    /**
     * @return iterable<SalesChannelContext>
     */
    public function createAll(Context $context, bool $onlyActive = false): iterable
    {
        $list = [];

        foreach ($this->getSalesChannels($context, $onlyActive) as $salesChannel) {
            foreach ($salesChannel->getLanguages() as $language) {
                $list[] = $this->create($salesChannel->getId(), $language->getId());
            }
        }

        return $list;
    }

    /**
     * @return iterable<SalesChannelEntity>
     */
    private function getSalesChannels(Context $context, bool $onlyActive): iterable
    {
        $criteria = new Criteria();
        $criteria->addAssociation('languages');

        if ($onlyActive) {
            $criteria->addFilter(new EqualsFilter('active', true));
        }

        return $this->salesChannelRepository
            ->search($criteria, $context)
            ->filter(fn (SalesChannelEntity $entity): bool => $this->config->isConfigured($entity->getId()));
    }
}
