<?php

declare(strict_types=1);

namespace Makaira\Connect\PersistenceLayer\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

final class UpdateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'makaira.persistence_layer.update';
    }

    public static function getDefaultInterval(): int
    {
        return 60;
    }
}
