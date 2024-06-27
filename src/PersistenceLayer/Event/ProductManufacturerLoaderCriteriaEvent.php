<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\ShopwareSalesChannelEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class ProductManufacturerLoaderCriteriaEvent extends NestedEvent implements ShopwareSalesChannelEvent
{
    public function __construct(
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $context,
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
