<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Traits\MediaTrait;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagEntity;

final readonly class ProductNormalizer implements NormalizerInterface
{
    use CustomFieldsTrait;
    use MediaTrait;

    /**
     * @param EntityRepository<ProductReviewCollection> $productReviewRepository
     */
    public function __construct(private EntityRepository $productReviewRepository, private UrlGenerator $urlGenerator)
    {
    }

    public function normalize(Entity $entity, SalesChannelContext $context): array
    {
        \assert($entity instanceof SalesChannelProductEntity);

        $categories = $entity->getCategories()->map(fn (CategoryEntity $category): array => [
            'catid' => $category->getId(),
            'title' => $category->getName(),
            'shopid' => 1,
            'pos' => 0,
            'path' => '',
        ]);

        $images = $entity->getMedia()->fmap(function (ProductMediaEntity $media): ?array {
            return $this->processMedia($media->getMedia());
        });

        return [
            'id' => $entity->getId(),
            'type' => null !== $entity->getParentId() ? 'variant' : 'product',
            'parent' => $entity->getParentId() ?? '',
            'isVariant' => null !== $entity->getParentId(),
            'shop' => 1,
            'ean' => $entity->getEan() ?? '',
            'active' => (bool) $entity->getActive(),
            'stock' => $entity->getAvailableStock(),
            'onstock' => 0 < $entity->getAvailableStock(),
            'productNumber' => $entity->getProductNumber(),
            'title' => $entity->getTranslation('name'),
            'longdesc' => $entity->getTranslation('description'),
            'keywords' => $entity->getTranslation('keywords'),
            'meta_title' => $entity->getTranslation('metaTitle'),
            'meta_description' => $entity->getTranslation('metaDescription'),
            'attributeStr' => $this->getGroupedOptions($entity->getProperties(), $entity->getOptions()),
            'category' => array_values($categories),
            'width' => $entity->getWidth(),
            'height' => $entity->getHeight(),
            'length' => $entity->getLength(),
            'weight' => $entity->getWeight(),
            'packUnit' => $entity->getTranslation('packUnit'),
            'packUnitPlural' => $entity->getTranslation('packUnitPlural'),
            'referenceUnit' => $entity->getReferenceUnit(),
            'purchaseUnit' => $entity->getPurchaseUnit(),
            'manufacturerid' => $entity->getManufacturerId(),
            'manufacturer_title' => $entity->getManufacturer()?->getName(),
            'ratingAverage' => $entity->getRatingAverage(),
            'totalProductReviews' => $this->countProductReviews($entity->getId(), $context->getContext()),
            'customFields' => $this->processCustomFields($entity->getCustomFields()),
            'topseller' => $entity->getMarkAsTopseller(),
            'searchable' => true,
            'searchkeys' => $this->getSearchKeys($entity),
            'tags' => $entity->getTags()->map(fn (TagEntity $tag): string => $tag->getName()),
            'unit' => $entity->getUnit()?->getShortCode(),
            'price' => $entity->getCalculatedPrice()->getUnitPrice(),
            'referencePrice' => $entity->getCalculatedPrice()->getReferencePrice()?->getPrice(),
            'images' => array_values($images),
            'url' => $this->urlGenerator->generate($entity, $context),
            'timestamp' => ($entity->getUpdatedAt() ?? $entity->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    private function getSearchKeys(ProductEntity $product): ?string
    {
        $searchKeys = $product->getSearchKeywords()->map(fn (ProductSearchKeywordEntity $item): string => $item->getKeyword());
        if ($product->getTranslation('customSearchKeywords')) {
            $searchKeys[] = $product->getTranslation('customSearchKeywords');
        }

        return 0 < \count($searchKeys) ? implode(' ', $searchKeys) : null;
    }

    private function getGroupedOptions(?PropertyGroupOptionCollection $properties, ?PropertyGroupOptionCollection $options): array
    {
        $grouped = [];

        foreach ($properties ?? [] as $property) {
            $group = $property->getGroup();
            if (!isset($grouped[$group->getId()])) {
                $grouped[$group->getId()] = [
                    'id' => $group->getId(),
                    'title' => $group->getTranslation('name'),
                    'value' => [],
                ];
            }

            $grouped[$group->getId()]['value'][] = $property->getTranslation('name');
        }

        foreach ($options ?? [] as $option) {
            $group = $option->getGroup();
            if (!isset($grouped[$group->getId()])) {
                $grouped[$group->getId()] = [
                    'id' => $group->getId(),
                    'title' => $group->getTranslation('name'),
                    'value' => [],
                ];
            }

            $grouped[$group->getId()]['value'][] = $option->getTranslation('name');
        }

        return array_values($grouped);
    }

    private function countProductReviews(string $productId, Context $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('productId', $productId));
        $criteria->addFilter(new EqualsFilter('status', true));

        return $this->productReviewRepository->searchIds($criteria, $context)->getTotal();
    }
}
