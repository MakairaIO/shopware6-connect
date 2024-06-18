<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\UrlHelper;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

final readonly class UrlGenerator
{
    public function __construct(
        private SalesChannelRepository $seoUrlRepository,
        private RouterInterface $router,
        private UrlHelper $urlHelper,
        private PluginConfig $config,
    ) {
    }

    public function generate(string $routeName, string $paramName, string $entityId, SalesChannelContext $context): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('routeName', $routeName));
        $criteria->addFilter(new EqualsFilter('foreignKey', $entityId));

        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = $this->seoUrlRepository->search($criteria, $context)->first();

        $relativeUrl = $seoUrl
            ? $seoUrl->getSeoPathInfo()
            : $this->router->generate($routeName, [$paramName => $entityId], UrlGeneratorInterface::RELATIVE_PATH);

        return 'absolute' === $this->config->getIndexUrlMode()
            ? $this->urlHelper->getAbsoluteUrl($relativeUrl)
            : $relativeUrl;
    }
}
