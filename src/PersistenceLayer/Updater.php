<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer;

use Doctrine\DBAL\Connection;
use Ixomo\MakairaConnect\Api\ApiGatewayFactory;
use Ixomo\MakairaConnect\PersistenceLayer\Event\EntityNormalizedEvent;
use Ixomo\MakairaConnect\PersistenceLayer\History\HistoryManager;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\InvalidDataException;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\Exception\UnsupportedException;
use Ixomo\MakairaConnect\PersistenceLayer\Normalizer\NormalizerRegistry;
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
            $addToLog = false;

            $normalized = $this->normalizeEntity($entityReference, $context);

            $lastSentData = $this->historyManager->getLastSentData($entityReference, $context);
            $filteredData = $normalized;
            if (null !== $lastSentData) {
                foreach ($filteredData as $field => $value) {
                    if (
                        !\in_array($field, ['id', 'type'], true)
                        && \array_key_exists($field, $lastSentData)
                        && $lastSentData[$field] === $value
                    ) {
                        unset($filteredData[$field]);
                    }
                }

                if (2 < \count($filteredData)) {
                    $apiGateway->updatePersistenceRevision($filteredData, $this->getLanguageCode($context));

                    $addToLog = true;
                }
            } else {
                $bulkInsert[] = [
                    'language' => $this->getLanguageCode($context),
                    'data' => $filteredData,
                ];

                $addToLog = true;
            }

            if ($addToLog) {
                $log[] = [
                    'entityId' => $entityReference->getEntityId(),
                    'entityName' => $entityReference->getEntityName(),
                    'data' => $normalized,
                    'sentAt' => $sentAt,
                ];
            }
        }

        if (0 < \count($bulkInsert)) {
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

        if (0 < \count($items)) {
            $apiGateway->deletePersistenceRevisions($items, $this->getLanguageCode($context));
        }
    }

    /**
     * @throws UnsupportedException If the entity is not supported by any normalizers
     * @throws InvalidDataException If the normalized data is invalid
     */
    private function normalizeEntity(EntityReference $entityReference, SalesChannelContext $context): array
    {
        $normalized = $this->normalizerRegistry
            ->getNormalizer($entityReference->getEntityName())
            ->normalize($entityReference->getEntityId(), $context);

        $event = new EntityNormalizedEvent($entityReference->getEntityName(), $entityReference->getEntityId(), $normalized);
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
