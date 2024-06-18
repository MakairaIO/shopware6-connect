<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tag\TagEntity;

final readonly class ProductNormalizer implements NormalizerInterface
{
    /**
     * @param SalesChannelRepository<ProductCollection> $repository
     */
    public function __construct(
        private SalesChannelRepository $repository,
        private UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @throws NotFoundException
     */
    public function normalize(string $entityId, SalesChannelContext $context): array
    {
        $product = $this->loadEntity($entityId, $context);

        $customFields = array_map(
            fn ($value) => \is_string($value) ? $this->removeEscapeCharacters($value) : $value,
            $product->getCustomFields() ?? []
        );

        $categories = $product->getCategories()->map(fn (CategoryEntity $category): array => [
            'catid' => $category->getId(),
            'title' => $category->getName(),
            'shopid' => 1,
            'pos' => 0,
            'path' => '',
        ]);

        return [
            'id' => $entityId,
            'type' => null !== $product->getParentId() ? 'variant' : 'product',
            'parent' => $product->getParentId() ?? '',
            'isVariant' => null !== $product->getParentId(),
            'shop' => 1,
            'ean' => $product->getEan() ?? '',
            'active' => (bool) $product->getActive(),
            'stock' => $product->getAvailableStock(),
            'onstock' => 0 < $product->getAvailableStock(),
            'productNumber' => $product->getProductNumber(),
            'title' => $product->getTranslation('name'),
            'longdesc' => $product->getTranslation('description'),
            'keywords' => $product->getTranslation('keywords'),
            'meta_title' => $product->getTranslation('metaTitle'),
            'meta_description' => $product->getTranslation('metaDescription'),
            'attributeStr' => $this->getGroupedOptions($product->getProperties(), $product->getOptions()),
            'category' => array_values($categories),
            'width' => $product->getWidth(),
            'height' => $product->getHeight(),
            'length' => $product->getLength(),
            'weight' => $product->getWeight(),
            'packUnit' => $product->getTranslation('packUnit'),
            'packUnitPlural' => $product->getTranslation('packUnitPlural'),
            'referenceUnit' => $product->getReferenceUnit(),
            'purchaseUnit' => $product->getPurchaseUnit(),
            'manufacturerid' => $product->getManufacturerId(),
            'manufacturer_title' => $product->getManufacturer()?->getName(),
            'customFields' => $customFields,
            'topseller' => $product->getMarkAsTopseller(),
            'searchable' => true,
            'searchkeys' => $this->getSearchKeys($product),
            'tags' => $product->getTags()->map(fn (TagEntity $tag): string => $tag->getName()),
            'price' => $product->getCalculatedPrice()->getUnitPrice(),
            'images' => array_values($product->getMedia()->fmap(fn (ProductMediaEntity $media): ?string => $media->getMedia()?->getUrl())),
            'url' => $this->urlGenerator->generate('frontend.detail.page', 'productId', $product->getId(), $context),
            'timestamp' => ($product->getUpdatedAt() ?? $product->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return ProductDefinition::ENTITY_NAME;
    }

    private function loadEntity(string $entityId, SalesChannelContext $context): SalesChannelProductEntity
    {
        $criteria = new Criteria([$entityId]);
        $criteria->addAssociation('media.media');
        $criteria->addAssociation('configuratorSettings');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('properties.group');
        $criteria->addAssociation('categories');
        $criteria->addAssociation('tags');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('searchKeywords');

        $entity = $this->repository->search($criteria, $context)->first();
        if (null === $entity) {
            throw NotFoundException::entity(self::getSupportedEntity(), $entityId);
        }

        return $entity;
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

    private function removeEscapeCharacters(string $string): string
    {
        return json_encode(json_decode($string, true), \JSON_UNESCAPED_UNICODE);
    }
}
