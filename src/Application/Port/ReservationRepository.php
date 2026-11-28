<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\PendingReservation;
use Library\Domain\ReservationSnapshot;

interface ReservationRepository
{
    /** Reads the reservation and locks it until the end of the transaction. */
    public function findForUpdate(int $id): ?ReservationSnapshot;

    /** Id of the assigned reservation of this member for this copy, if there is one. */
    public function findAssignedId(int $copyId, int $memberId): ?int;

    public function hasPending(int $bookId, int $memberId): bool;

    /**
     * Pending reservations of a book with the name of the member who made each one,
     * in no particular order.
     *
     * @return PendingReservation[]
     */
    public function pendingOfBook(int $bookId): array;

    /** Registers a pending reservation and returns its id. */
    public function add(int $bookId, int $memberId, \DateTimeImmutable $reservedAt): int;

    /** Gives the copy to the reservation. */
    public function assign(int $id, int $copyId): void;

    public function markFulfilled(int $id): void;

    public function cancel(int $id): void;
}
