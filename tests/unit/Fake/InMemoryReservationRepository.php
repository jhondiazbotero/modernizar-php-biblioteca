<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\ReservationRepository;
use Library\Domain\PendingReservation;
use Library\Domain\ReservationSnapshot;

final class InMemoryReservationRepository implements ReservationRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function findForUpdate(int $id): ?ReservationSnapshot
    {
        if (!isset($this->store->reservations[$id])) {
            return null;
        }
        $r = $this->store->reservations[$id];

        return new ReservationSnapshot($id, $r['bookId'], $r['memberId'], $r['copyId'], $r['status']);
    }

    public function findAssignedId(int $copyId, int $memberId): ?int
    {
        foreach ($this->store->reservations as $id => $r) {
            if ($r['copyId'] === $copyId && $r['memberId'] === $memberId && $r['status'] === ReservationSnapshot::ASSIGNED) {
                return $id;
            }
        }

        return null;
    }

    public function hasPending(int $bookId, int $memberId): bool
    {
        foreach ($this->store->reservations as $r) {
            if ($r['bookId'] === $bookId && $r['memberId'] === $memberId && $r['status'] === ReservationSnapshot::PENDING) {
                return true;
            }
        }

        return false;
    }

    public function pendingOfBook(int $bookId): array
    {
        $pending = [];
        foreach ($this->store->reservations as $id => $r) {
            if ($r['bookId'] === $bookId && $r['status'] === ReservationSnapshot::PENDING) {
                $name = $this->store->members[$r['memberId']]->getName();
                $pending[] = new PendingReservation($id, $r['memberId'], $name, $r['reservedAt']);
            }
        }

        return $pending;
    }

    public function add(int $bookId, int $memberId, \DateTimeImmutable $reservedAt): int
    {
        $this->store->write('reservations.add');
        $id = $this->store->nextReservationId++;
        $this->store->reservations[$id] = [
            'bookId' => $bookId,
            'memberId' => $memberId,
            'reservedAt' => $reservedAt,
            'copyId' => null,
            'status' => ReservationSnapshot::PENDING,
        ];

        return $id;
    }

    public function assign(int $id, int $copyId): void
    {
        $this->store->write('reservations.assign');
        $this->store->reservations[$id]['status'] = ReservationSnapshot::ASSIGNED;
        $this->store->reservations[$id]['copyId'] = $copyId;
    }

    public function markFulfilled(int $id): void
    {
        $this->store->write('reservations.markFulfilled');
        $this->store->reservations[$id]['status'] = ReservationSnapshot::FULFILLED;
    }

    public function cancel(int $id): void
    {
        $this->store->write('reservations.cancel');
        $this->store->reservations[$id]['status'] = ReservationSnapshot::CANCELLED;
    }
}
