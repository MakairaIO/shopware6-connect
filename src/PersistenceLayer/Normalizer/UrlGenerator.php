<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PluginConfig;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class UrlGenerator
{
    public function __construct(
        private SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandler,
        private AbstractCategoryUrlGenerator $categoryUrlGenerator,
        private PluginConfig $config,
    ) {
    }

    public function generate(SalesChannelProductEntity|CategoryEntity $entity, SalesChannelContext $context): ?string
    {
        $urlTemplate = match ($entity::class) {
            SalesChannelProductEntity::class => $this->seoUrlPlaceholderHandler->generate(
                'frontend.detail.page',
                ['productId' => $entity->getId()]
            ),
            CategoryEntity::class => $this->categoryUrlGenerator->generate($entity, $context->getSalesChannel()),
        };

        if (null === $urlTemplate) {
            return null;
        }

        $domain = $this->getDomain($context);
        $absoluteUrl = $this->seoUrlPlaceholderHandler->replace($urlTemplate, $domain, $context);

        return ('relative' === $this->config->getIndexUrlMode() && str_starts_with($absoluteUrl, $domain))
            ? mb_substr($absoluteUrl, mb_strlen($domain))
            : $absoluteUrl;
    }

    private function getDomain(SalesChannelContext $context): string
    {
        $entity = $context->getSalesChannel()->getDomains()->filterByProperty('languageId', $context->getLanguageId())->first();

        if (null === $entity) {
            throw new \RuntimeException('Domain not found');
        }

        return $entity->getUrl();
    }
}
