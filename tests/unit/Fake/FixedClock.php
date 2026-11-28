<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Domain\Clock;

final class FixedClock implements Clock
{
    /** @var \DateTimeImmutable */
    private $now;

    public function __construct(string $moment)
    {
        $this->now = new \DateTimeImmutable($moment);
    }

    public function today(): \DateTimeImmutable
    {
        return $this->now->setTime(0, 0, 0);
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
