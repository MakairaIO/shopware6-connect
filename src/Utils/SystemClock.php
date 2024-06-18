<?php

declare(strict_types=1);

namespace Ixomo\MakairaConnect\Utils;

use Psr\Clock\ClockInterface;

class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
