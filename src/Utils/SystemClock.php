<?php

namespace Ixomo\MakairaConnect\Utils;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

class SystemClock implements ClockInterface
{

    /**
     * @inheritDoc
     */
    public function now(): DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }
}
