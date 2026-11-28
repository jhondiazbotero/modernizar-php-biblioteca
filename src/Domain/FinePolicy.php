<?php

declare(strict_types=1);

namespace Library\Domain;

final class FinePolicy
{
    /**
     * Calendar days between the due date and the return date, times the fine per day;
     * nothing when the loan comes back on the due date or before. Both dates are taken
     * at midnight, so the time of day does not count.
     */
    public function fineFor(\DateTimeImmutable $dueDate, \DateTimeImmutable $returnedOn): int
    {
        $due = $dueDate->setTime(0, 0, 0);
        $returned = $returnedOn->setTime(0, 0, 0);
        if ($returned <= $due) {
            return 0;
        }

        return (int) $due->diff($returned)->days * LendingRules::FINE_PER_DAY;
    }
}
