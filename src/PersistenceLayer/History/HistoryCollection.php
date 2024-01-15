<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\PersistenceLayer\History;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<HistoryEntity>
 */
final class HistoryCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'ixomo_makaira_connect_history_collection';
    }

    protected function getExpectedClass(): string
    {
        return HistoryEntity::class;
    }
}
