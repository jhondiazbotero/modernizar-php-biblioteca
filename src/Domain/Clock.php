<?php

declare(strict_types=1);

namespace Library\Domain;

/** Source of the current date and time, so rules never read the system clock directly. */
interface Clock
{
    /** Today at 00:00. */
    public function today(): \DateTimeImmutable;

    public function now(): \DateTimeImmutable;
}
