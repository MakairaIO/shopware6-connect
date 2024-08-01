<?php

declare(strict_types=1);

namespace Makaira\Connect\DataAbstractionLayer;

use Makaira\Connect\PersistenceLayer\EntityReference;
use Makaira\Connect\PersistenceLayer\MessageQueue\Message\DeleteEntities;
use Makaira\Connect\SalesChannel\ContextFactory;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private MessageBusInterface $bus,
        private ContextFactory $contextFactory,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_DELETED_EVENT => 'onEntityDeleted',
            ProductEvents::PRODUCT_MANUFACTURER_DELETED_EVENT => 'onEntityDeleted',
            CategoryEvents::CATEGORY_DELETED_EVENT => 'onEntityDeleted',
        ];
    }

    public function onEntityDeleted(EntityDeletedEvent $event): void
    {
        foreach ($this->contextFactory->createAll($event->getContext()) as $context) {
            $entityReferences = [];
            foreach ($event->getIds() as $id) {
                $entityReferences[] = new EntityReference($event->getEntityName(), $id);
            }

            $this->bus->dispatch(new DeleteEntities($entityReferences, $context->getSalesChannelId(), $context->getLanguageId()));
        }
    }
}
