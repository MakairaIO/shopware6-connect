<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer;

use Doctrine\DBAL\Connection;
use Makaira\Connect\Api\ApiGatewayFactory;
use Makaira\Connect\PersistenceLayer\Event\EntityNormalizedEvent;
use Makaira\Connect\PersistenceLayer\History\HistoryManager;
use Makaira\Connect\PersistenceLayer\Normalizer\Exception\InvalidDataException;
use Makaira\Connect\PersistenceLayer\Normalizer\Exception\UnsupportedException;
use Makaira\Connect\PersistenceLayer\Normalizer\LoaderRegistry;
use Makaira\Connect\PersistenceLayer\Normalizer\NormalizerRegistry;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class Updater
{
    private array $languageCodeCache = [];

    public function __construct(
        private readonly Connection $database,
        private readonly LoaderRegistry $loaderRegistry,
        private readonly NormalizerRegistry $normalizerRegistry,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ApiGatewayFactory $apiGatewayFactory,
        private readonly HistoryManager $historyManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function update(EntityReferenceCollection $entityReferences, SalesChannelContext $context): void
    {
        if (0 === \count($entityReferences)) {
            return;
        }

        $apiGateway = $this->apiGatewayFactory->create($context);

        $log = [];
        $bulkInsert = [];

        $sentAt = $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        foreach ($entityReferences as $entityReference) {
            $normalized = $this->normalizeEntity($entityReference, $context);

            if ($this->updateRequired($entityReference, $normalized, $context)) {
                $bulkInsert[] = [
                    'language' => $this->getLanguageCode($context),
                    'data' => $normalized,
                ];

                $log[] = [
                    'entityId' => $entityReference->getEntityId(),
                    'entityName' => $entityReference->getEntityName(),
                    'data' => $normalized,
                    'sentAt' => $sentAt,
                ];
            }
        }

        if ([] !== $bulkInsert) {
            $apiGateway->insertPersistenceRevisions($bulkInsert);
        }

        $this->historyManager->saveSentData($log, $context);
    }

    public function delete(EntityReferenceCollection $entityReferences, SalesChannelContext $context): void
    {
        $apiGateway = $this->apiGatewayFactory->create($context);

        $items = $entityReferences->fmap(function (EntityReference $entityReference): ?array {
            $type = match ($entityReference->getEntityName()) {
                ProductDefinition::ENTITY_NAME => 'product',
                ProductManufacturerDefinition::ENTITY_NAME => 'manufacturer',
                CategoryDefinition::ENTITY_NAME => 'category',
                default => null,
            };

            if (null === $type) {
                return null;
            }

            return [
                'type' => $type,
                'id' => $entityReference->getEntityId(),
            ];
        });

        if ([] !== $items) {
            $apiGateway->deletePersistenceRevisions($items, $this->getLanguageCode($context));
        }
    }

    /**
     * @throws UnsupportedException If the entity is not supported by any normalizers
     * @throws InvalidDataException If the normalized data is invalid
     */
    private function normalizeEntity(EntityReference $entityReference, SalesChannelContext $context): array
    {
        $entity = $this->loaderRegistry
            ->getLoader($entityReference->getEntityName())
            ->load($entityReference->getEntityId(), $context);

        $normalized = $this->normalizerRegistry
            ->getNormalizer($entityReference->getEntityName())
            ->normalize($entity, $context);

        $event = new EntityNormalizedEvent($entityReference->getEntityName(), $entity, $normalized);
        $this->eventDispatcher->dispatch($event);

        $normalized = $event->getData();

        if (
            !\is_string($normalized['type']) || 0 === mb_strlen($normalized['type'])
            || !\is_string($normalized['id']) || 0 === mb_strlen($normalized['id'])
        ) {
            throw InvalidDataException::missingTypeOrId($entityReference);
        }

        return $normalized;
    }

    private function updateRequired(
        EntityReference $entityReference,
        array $normalizedData,
        SalesChannelContext $context,
    ): bool {
        $lastSentData = $this->historyManager->getLastSentData($entityReference, $context);

        if (null === $lastSentData) {
            return true;
        }

        foreach ($normalizedData as $field => $value) {
            if (
                !\in_array($field, ['id', 'type'], true)
                && \array_key_exists($field, $lastSentData)
                && $lastSentData[$field] === $value
            ) {
                unset($normalizedData[$field]);
            }
        }

        return 2 < \count($normalizedData);
    }

    private function getLanguageCode(SalesChannelContext $context): string
    {
        $languageId = $context->getLanguageId();

        if (!\array_key_exists($languageId, $this->languageCodeCache)) {
            $queryBuilder = $this->database->createQueryBuilder();
            $languageCode = $queryBuilder->select('SUBSTRING(locale.code, 1, 2)')
                ->from('language')
                ->join('language', 'locale', 'locale', 'language.locale_id = locale.id')
                ->where(
                    $queryBuilder->expr()->eq('language.id', $queryBuilder->createNamedParameter(Uuid::fromHexToBytes($languageId)))
                )
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne() ?: null;

            if (null === $languageCode) {
                throw new \Exception('Could not get language-code for language ' . $languageId);
            }

            $this->languageCodeCache[$languageId] = $languageCode;
        }

        return $this->languageCodeCache[$languageId];
    }
}
