<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

use Ixomo\MakairaConnect\Api\Exception\ApiException;
use Psr\Clock\ClockInterface;

final class ApiGateway implements ApiGatewayInterface
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly ClockInterface $clock,
        private readonly string $customer,
        private readonly string $instance,
    ) {
    }

    public function insertPersistenceRevision(array $data, string $language): void
    {
        $this->insertPersistenceRevisions([
            [
                'data' => $data,
                'language' => $language,
            ],
        ]);
    }

    public function insertPersistenceRevisions(array $items): void
    {
        if (0 === \count($items)) {
            return;
        }

        $response = $this->apiClient->request('PUT', '/persistence/revisions', null, [
            'import_timestamp' => $this->clock->now()->format('Y-m-d H:i:s'),
            'items' => array_map(fn (array $item): array => [
                'source_revision' => 1,
                'language_id' => $item['language'],
                'data' => $item['data'],
            ], $items),
        ]);

        if ('success' !== ($response->toArray(false)['status'] ?? null)) {
            throw ApiException::fromResponse($response);
        }
    }

    public function updatePersistenceRevision(array $data, string $language): void
    {
        $response = $this->apiClient->request('PATCH', '/persistence/revisions', null, [
            'language_id' => $language,
            'data' => $data,
        ]);

        if ('success' !== ($response->toArray(false)['status'] ?? null)) {
            throw ApiException::fromResponse($response);
        }
    }

    public function deletePersistenceRevisions(array $items, string $language): void
    {
        $response = $this->apiClient->request('PUT', '/persistence/revisions', null, [
            'items' => array_map(fn (array $data): array => [
                'language_id' => $language,
                'delete' => true,
                'data' => $data,
            ], $items),
        ]);

        if ('success' !== ($response->toArray(false)['status'] ?? null)) {
            throw ApiException::fromResponse($response);
        }
    }

    public function rebuildPersistenceLayer(): void
    {
        $response = $this->apiClient->request('POST', '/persistence/revisions/rebuild', [
            'customer' => $this->customer,
            'instance' => $this->instance,
        ]);

        if ('success' !== ($response->toArray(false)['status'] ?? null)) {
            throw ApiException::fromResponse($response);
        }
    }

    public function switchPersistenceLayer(): void
    {
        $response = $this->apiClient->request('POST', '/persistence/revisions/switch', [
            'customer' => $this->customer,
            'instance' => $this->instance,
        ]);

        if ('success' !== ($response->toArray(false)['status'] ?? null)) {
            throw ApiException::fromResponse($response);
        }
    }
}
