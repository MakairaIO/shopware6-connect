<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Api;

interface ApiGatewayInterface
{
    public function insertPersistenceRevision(array $data, string $language): void;

    public function insertPersistenceRevisions(array $items): void;

    public function updatePersistenceRevision(array $data, string $language): void;

    public function deletePersistenceRevisions(array $items, string $language): void;

    public function rebuildPersistenceLayer(): void;

    public function switchPersistenceLayer(): void;
}
