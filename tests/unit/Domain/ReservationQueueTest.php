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

    /** Current behavior, including known defect F2: queue ordered by member name. */
    public function test_f2_the_first_name_in_alphabetical_order_wins_even_if_it_arrived_last(): void
    {
        $pending = [
            $this->pending(1, 'Marta Pérez', '2026-11-01 08:00:00'),
            $this->pending(2, 'Beatriz Gómez', '2026-11-20 08:00:00'),
            $this->pending(3, 'Carlos Ruiz', '2026-11-05 08:00:00'),
        ];

        self::assertSame(2, $this->nextId($pending));
    }

    public function test_the_order_of_the_list_does_not_matter(): void
    {
        $a = $this->pending(1, 'Diana');
        $b = $this->pending(2, 'Ana');
        $c = $this->pending(3, 'Camila');

        self::assertSame(2, $this->nextId([$a, $b, $c]));
        self::assertSame(2, $this->nextId([$c, $b, $a]));
        self::assertSame(2, $this->nextId([$b, $c, $a]));
    }

    public function test_an_accent_does_not_change_the_order_like_in_mysql(): void
    {
        // «Álvaro» < «Andrés» because «l» < «n»; the accent on the first letter is ignored
        self::assertSame(2, $this->nextId([$this->pending(1, 'Andrés'), $this->pending(2, 'Álvaro')]));
        self::assertSame(2, $this->nextId([$this->pending(2, 'Álvaro'), $this->pending(1, 'Andrés')]));
    }

    public function test_upper_and_lower_case_are_equal_so_the_lowest_id_wins(): void
    {
        // «Andrés» = «andrés»: tie, the lower id wins whatever the position in the list
        self::assertSame(3, $this->nextId([$this->pending(5, 'andrés'), $this->pending(3, 'Andrés'), $this->pending(4, 'ANDRÉS')]));
        self::assertSame(3, $this->nextId([$this->pending(3, 'Andrés'), $this->pending(5, 'andrés')]));
    }

    public function test_álvaro_andrés_and_andrés_in_lowercase_follow_the_mysql_order(): void
    {
        $alvaro = $this->pending(30, 'Álvaro');
        $andres = $this->pending(20, 'Andrés');
        $andresLower = $this->pending(10, 'andrés');

        self::assertSame(30, $this->nextId([$andresLower, $andres, $alvaro]));
        // without «Álvaro»: «Andrés» and «andrés» are equal and the lower id wins
        self::assertSame(10, $this->nextId([$andres, $andresLower]));
    }

    public function test_the_tie_between_the_same_name_in_different_case_goes_to_the_lower_id(): void
    {
        self::assertSame(10, $this->nextId([$this->pending(20, 'Andrés'), $this->pending(10, 'andrés')]));
    }

    public function test_n_with_tilde_sorts_as_n_like_utf8mb4_unicode_ci(): void
    {
        // «Muñoz» between «Munera» and «Murillo» ignoring the tilde: «muñoz» < «murillo»
        self::assertSame(2, $this->nextId([$this->pending(1, 'Murillo'), $this->pending(2, 'Muñoz')]));
    }

    public function test_trailing_spaces_are_ignored_like_in_mysql(): void
    {
        self::assertSame(1, $this->nextId([$this->pending(2, 'Ana  '), $this->pending(1, 'ana')]));
    }

    public function test_a_prefix_comes_before_the_longer_name(): void
    {
        self::assertSame(2, $this->nextId([$this->pending(1, 'Ana María'), $this->pending(2, 'Ana')]));
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
