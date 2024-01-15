<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final class UrlGenerator
{
    public function __construct(
        private readonly SalesChannelRepository $seoUrlRepository,
        private readonly RouterInterface $router,
        private readonly UrlHelper $urlHelper,
    ) {
    }

    public function generate(string $routeName, string $paramName, string $entityId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('foreignKey', $entityId));

        /** @var SeoUrlEntity $seoUrl */
        $seoUrl = $this->seoUrlRepository->search($criteria, $context)->first();

        if ($seoUrl) {
            return $this->urlHelper->getAbsoluteUrl($seoUrl->getSeoPathInfo());
        } else {
            return $this->router->generate($routeName, [$paramName => $entityId], UrlGeneratorInterface::ABSOLUTE_URL);
        }
    }
}
