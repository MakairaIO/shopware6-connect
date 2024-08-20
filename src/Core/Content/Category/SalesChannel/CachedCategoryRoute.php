<?php

declare(strict_types=1);

namespace Makaira\Connect\Core\Content\Category\SalesChannel;

use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRouteResponse;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Package('content')]
#[Route(defaults: ['_routeScope' => ['store-api']])]
class CachedCategoryRoute extends AbstractCategoryRoute
{
    public function __construct(
        protected readonly AbstractCategoryRoute $decorated,
    ) {
    }

    public function getDecorated(): AbstractCategoryRoute
    {
        return $this->decorated;
    }

    public function load(string $navigationId, Request $request, SalesChannelContext $context): CategoryRouteResponse
    {
        // remove slots to get all cms elements we are interested in filter and listing
        // TODO: optimize to only return filter and listing elements
        $request->query->set('slots', null);

        // dont use the cache layer
        return $this->getDecorated()->load($navigationId, $request, $context);
    }
}
