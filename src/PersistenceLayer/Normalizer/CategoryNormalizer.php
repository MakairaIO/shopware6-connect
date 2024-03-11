<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class CategoryNormalizer implements NormalizerInterface
{
    /**
     * @param SalesChannelRepository<CategoryCollection> $repository
     */
    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly UrlGenerator $urlGenerator,
    ) {
    }

    /**
     * @throws NotFoundException
     */
    public function normalize(string $entityId, SalesChannelContext $context): array
    {
        $category = $this->loadEntity($entityId, $context);

        return [
            'id' => $entityId,
            'type' => 'category',
            'shop' => 1,
            'category_title' => $category->getTranslation('name'),
            'level' => $category->getLevel(),
            'parent' => $category->getParentId() ?? '',
            'subcategories' => $this->getSubcategories($category, $context),
            'hierarchy' => $this->getHierarchy($category),
            'customFields' => $category->getCustomFields(),
            'sorting' => $this->getSorting($category, $context),
            'active' => $category->getActive(),
            'hidden' => !$category->getVisible(),
            'url' => $this->urlGenerator->generate('frontend.navigation.page', 'navigationId', $category->getId(), $context),
            'timestamp' => ($category->getUpdatedAt() ?? $category->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }

    private function loadEntity(string $entityId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$entityId]);

        $entity = $this->repository->search($criteria, $context)->first();
        if (null === $entity) {
            throw NotFoundException::entity($this->getSupportedEntity(), $entityId);
        }

        return $entity;
    }

    private function getSubcategories(CategoryEntity $category, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $category->getId()));

        return $this->repository->searchIds($criteria, $context)->getIds();
    }

    private function getHierarchy(CategoryEntity $category): string
    {
        $hierarchy = null !== $category->getPath() ? \array_slice(explode('|', $category->getPath()), 1, -1) : [];
        $hierarchy[] = $category->getId();

        return implode('//', $hierarchy);
    }

    private function getSorting(CategoryEntity $category, SalesChannelContext $context): int
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('level', $category->getLevel()));

        /** @var array<CategoryEntity> $searchResult */
        $searchResult = $this->repository->search($criteria, $context)->getElements();

        $collection = new CategoryCollection($searchResult);
        $collection->sortByPosition();

        return array_search($category->getId(), array_values($collection->getIds())) + 1;
    }
}
