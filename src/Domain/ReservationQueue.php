<?php

declare(strict_types=1);

namespace Library\Domain;

final class ReservationQueue
{
    /**
     * @param PendingReservation[] $pending
     *
     * Current behavior, including known defect F2 (queue ordered by member name).
     */
    public function next(array $pending): ?PendingReservation
    {
        $next = null;
        foreach ($pending as $candidate) {
            if ($next === null || $this->comesBefore($candidate, $next)) {
                $next = $candidate;
            }
        }

        return $next;
    }

    private function comesBefore(PendingReservation $a, PendingReservation $b): bool
    {
        $byName = NameCollation::compare($a->getMemberName(), $b->getMemberName());

        return $byName !== 0 ? $byName < 0 : $a->getId() < $b->getId();
    }
}
