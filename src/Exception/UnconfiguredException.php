<?php

declare(strict_types=1);

namespace Makaira\Connect\Exception;

class UnconfiguredException extends \Exception
{
    public static function api(string $salesChannelId): self
    {
        return new self('The Makaira API is unconfigured for sales-channel "' . $salesChannelId . '"');
    }
}
