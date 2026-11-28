<?php

declare(strict_types=1);

namespace Library\Domain;

final class FinePolicy
{
    /** Current behavior, including known defect F1 (days counted by day of month). */
    public function fineFor(\DateTimeImmutable $dueDate, \DateTimeImmutable $returnedOn): int
    {
        $due = $dueDate->setTime(0, 0, 0);
        $returned = $returnedOn->setTime(0, 0, 0);
        if ($returned <= $due) {
            return 0;
        }

        $days = (int) $returned->format('j') - (int) $due->format('j');

        return $days < 0 ? 0 : $days * LendingRules::FINE_PER_DAY;
    }
}
