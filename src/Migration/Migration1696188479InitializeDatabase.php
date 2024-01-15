<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1696188479InitializeDatabase extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696188479;
    }

    public function update(Connection $connection): void
    {
        $query = <<<SQL
            CREATE TABLE IF NOT EXISTS `ixomo_makaira_connect_history` (
                `id` binary(16) NOT NULL,
                `sales_channel_id` binary(16) NOT NULL,
                `language_id` binary(16) NOT NULL,
                `entity_name` varchar(255) NOT NULL,
                `entity_id` binary(16) NOT NULL,
                `data` longtext NOT NULL,
                `sent_at` datetime(3) NOT NULL,
                `created_at` datetime(3) DEFAULT NULL,
                `updated_at` datetime(3) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `custom.select_last_sent_data` (`sales_channel_id`, `language_id`, `entity_name`, `entity_id`, `sent_at`),
                KEY `fk.ixomo_makaira_connect_history.sales_channel_id` (`sales_channel_id`),
                KEY `fk.ixomo_makaira_connect_history.language_id` (`language_id`),
                CONSTRAINT `fk.ixomo_makaira_connect_history.sales_channel_id` FOREIGN KEY (`sales_channel_id`) REFERENCES `sales_channel` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk.ixomo_makaira_connect_history.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
                CONSTRAINT `json.ixomo_makaira_connect_history.data` CHECK (json_valid(`data`))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL;

        $connection->executeStatement($query);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
