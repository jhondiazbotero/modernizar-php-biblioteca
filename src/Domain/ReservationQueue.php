<?php

declare(strict_types=1);

namespace Library\Domain;

final class ReservationQueue
{
    /**
     * The next reservation is the one made first (oldest `reservedAt`); on the same
     * moment, the one with the lowest id.
     *
     * @param PendingReservation[] $pending
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
        if ($a->getReservedAt() != $b->getReservedAt()) {
            return $a->getReservedAt() < $b->getReservedAt();
        }

        return $a->getId() < $b->getId();
    }
}
