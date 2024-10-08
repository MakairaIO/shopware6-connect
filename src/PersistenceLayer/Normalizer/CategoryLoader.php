<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\Normalizer;

use Makaira\Connect\PersistenceLayer\Event\CategoryLoaderCriteriaEvent;
use Makaira\Connect\PersistenceLayer\Normalizer\Exception\NotFoundException;
use Makaira\Connect\PersistenceLayer\Normalizer\Traits\CustomFieldsTrait;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class CategoryLoader implements LoaderInterface
{
    use CustomFieldsTrait;

    /**
     * @param SalesChannelRepository<CategoryCollection> $repository
     */
    public function __construct(
        private SalesChannelRepository $repository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $entityId, SalesChannelContext $context): CategoryEntity
    {
        $criteria = new Criteria([$entityId]);
        $criteria->addAssociation('media');

        $this->eventDispatcher->dispatch(new CategoryLoaderCriteriaEvent($criteria, $context));

        $entity = $this->repository->search($criteria, $context)->first();
        if (null === $entity) {
            throw NotFoundException::entity(self::getSupportedEntity(), $entityId);
        }

        return $entity;
    }

    public static function getSupportedEntity(): string
    {
        return CategoryDefinition::ENTITY_NAME;
    }
}
