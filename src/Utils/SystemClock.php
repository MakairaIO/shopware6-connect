<?php

declare(strict_types=1);

namespace Makaira\Connect\Utils;

use Psr\Clock\ClockInterface;

class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
