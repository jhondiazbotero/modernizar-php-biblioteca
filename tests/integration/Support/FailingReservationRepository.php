<?php

declare(strict_types=1);

namespace Library\Tests\Integration\Support;

use Library\Application\Port\ReservationRepository;
use Library\Domain\ReservationSnapshot;

/** Reads and writes like the real repository, except that it fails when a reservation is assigned. */
final class FailingReservationRepository implements ReservationRepository
{
    /** @var ReservationRepository */
    private $inner;

    public function __construct(ReservationRepository $inner)
    {
        $this->inner = $inner;
    }

    public function findForUpdate(int $id): ?ReservationSnapshot
    {
        return $this->inner->findForUpdate($id);
    }

    public function findAssignedId(int $copyId, int $memberId): ?int
    {
        return $this->inner->findAssignedId($copyId, $memberId);
    }

    public function hasPending(int $bookId, int $memberId): bool
    {
        return $this->inner->hasPending($bookId, $memberId);
    }

    public function pendingOfBook(int $bookId): array
    {
        return $this->inner->pendingOfBook($bookId);
    }

    public function add(int $bookId, int $memberId, \DateTimeImmutable $reservedAt): int
    {
        return $this->inner->add($bookId, $memberId, $reservedAt);
    }

    public function assign(int $id, int $copyId): void
    {
        throw new \RuntimeException('Forced failure while assigning the reservation.');
    }

    public function markFulfilled(int $id): void
    {
        $this->inner->markFulfilled($id);
    }

    public function cancel(int $id): void
    {
        $this->inner->cancel($id);
    }
}
