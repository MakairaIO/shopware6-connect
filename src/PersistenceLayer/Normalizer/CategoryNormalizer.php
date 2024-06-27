<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\Normalizer;

use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final readonly class CategoryNormalizer implements NormalizerInterface
{
    use CustomFieldsTrait;

    /**
     * @param SalesChannelRepository<CategoryCollection> $repository
     */
    public function __construct(
        private SalesChannelRepository $repository,
        private UrlGenerator $urlGenerator,
    ) {
    }

    public function normalize(Entity $entity, SalesChannelContext $context): array
    {
        \assert($entity instanceof CategoryEntity);

        return [
            'id' => $entity->getId(),
            'type' => 'category',
            'shop' => 1,
            'category_title' => $entity->getTranslation('name'),
            'level' => $entity->getLevel(),
            'parent' => $entity->getParentId() ?? '',
            'subcategories' => $this->getSubcategories($entity, $context),
            'hierarchy' => $this->getHierarchy($entity),
            'customFields' => $this->processCustomFields($entity->getCustomFields()),
            'sorting' => $this->getSorting($entity, $context),
            'active' => $entity->getActive(),
            'hidden' => !$entity->getVisible(),
            'url' => $this->urlGenerator->generate($entity, $context),
            'timestamp' => ($entity->getUpdatedAt() ?? $entity->getCreatedAt())->format('Y-m-d H:i:s'),
        ];
    }

    public static function getSupportedEntity(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }

    private function getSubcategories(CategoryEntity $category, SalesChannelContext $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $category->getId()));

        return $this->repository->searchIds($criteria, $context)->getIds();
    }

    private function getHierarchy(CategoryEntity $category): string
    {
        $hierarchy = null !== $category->getPath() ? \array_slice(explode('|', (string) $category->getPath()), 1, -1) : [];
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
