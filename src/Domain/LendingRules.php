<?php

declare(strict_types=1);

namespace Library\Domain;

/** Numbers that define how lending works in the library. */
final class LendingRules
{
    public const LOAN_DAYS = 14;
    public const MAX_ACTIVE_LOANS = 3;
    public const FINE_PER_DAY = 500;

    private function __construct()
    {
    }
}
