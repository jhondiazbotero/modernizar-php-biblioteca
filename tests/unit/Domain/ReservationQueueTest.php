<?php

declare(strict_types=1);

namespace Library\Tests\Domain;

use Library\Domain\PendingReservation;
use Library\Domain\ReservationQueue;
use PHPUnit\Framework\TestCase;

final class ReservationQueueTest extends TestCase
{
    private function pending(int $id, string $name, string $reservedAt = '2026-11-10 09:00:00'): PendingReservation
    {
        return new PendingReservation($id, $id + 100, $name, new \DateTimeImmutable($reservedAt));
    }

    /** @param PendingReservation[] $pending */
    private function nextId(array $pending): ?int
    {
        $next = (new ReservationQueue())->next($pending);

        return $next === null ? null : $next->getId();
    }

    public function test_an_empty_list_has_no_next(): void
    {
        self::assertNull((new ReservationQueue())->next([]));
    }

    public function test_a_single_reservation_is_the_next(): void
    {
        self::assertSame(7, $this->nextId([$this->pending(7, 'Zoe')]));
    }

    public function test_the_oldest_reservation_wins_even_if_its_name_comes_last(): void
    {
        $pending = [
            $this->pending(1, 'Marta Pérez', '2026-11-01 08:00:00'),
            $this->pending(2, 'Beatriz Gómez', '2026-11-20 08:00:00'),
            $this->pending(3, 'Carlos Ruiz', '2026-11-05 08:00:00'),
        ];

        self::assertSame(1, $this->nextId($pending));
    }

    public function test_the_oldest_reservation_wins_even_if_it_has_the_highest_id(): void
    {
        $pending = [
            $this->pending(1, 'Ana', '2026-11-12 08:00:00'),
            $this->pending(2, 'Beatriz', '2026-11-10 08:00:00'),
            $this->pending(3, 'Carlos', '2026-11-03 08:00:00'),
        ];

        self::assertSame(3, $this->nextId($pending));
    }

    public function test_the_time_of_the_day_decides_between_reservations_of_the_same_day(): void
    {
        $pending = [
            $this->pending(1, 'Ana', '2026-11-10 16:40:00'),
            $this->pending(2, 'Beatriz', '2026-11-10 09:15:00'),
        ];

        self::assertSame(2, $this->nextId($pending));
    }

    public function test_the_order_of_the_list_does_not_matter(): void
    {
        $a = $this->pending(1, 'Diana', '2026-11-03 08:00:00');
        $b = $this->pending(2, 'Ana', '2026-11-01 08:00:00');
        $c = $this->pending(3, 'Camila', '2026-11-02 08:00:00');

        self::assertSame(2, $this->nextId([$a, $b, $c]));
        self::assertSame(2, $this->nextId([$c, $b, $a]));
        self::assertSame(2, $this->nextId([$b, $c, $a]));
        self::assertSame(2, $this->nextId([$c, $a, $b]));
    }

    public function test_the_same_moment_goes_to_the_lowest_id_whatever_the_position_in_the_list(): void
    {
        $at = '2026-11-10 09:00:00';

        self::assertSame(3, $this->nextId([$this->pending(5, 'Zoe', $at), $this->pending(3, 'Beatriz', $at), $this->pending(4, 'Ana', $at)]));
        self::assertSame(3, $this->nextId([$this->pending(3, 'Beatriz', $at), $this->pending(5, 'Ana', $at)]));
    }

    public function test_the_same_moment_for_a_later_id_does_not_beat_an_earlier_moment(): void
    {
        $pending = [
            $this->pending(1, 'Ana', '2026-11-10 09:00:01'),
            $this->pending(9, 'Zoe', '2026-11-10 09:00:00'),
        ];

        self::assertSame(9, $this->nextId($pending));
    }

    public function test_a_pending_reservation_exposes_its_data(): void
    {
        $at = new \DateTimeImmutable('2026-11-10 09:15:00');
        $pending = new PendingReservation(4, 9, 'Ana', $at);

        self::assertSame(4, $pending->getId());
        self::assertSame(9, $pending->getMemberId());
        self::assertSame('Ana', $pending->getMemberName());
        self::assertSame($at, $pending->getReservedAt());
    }
}
