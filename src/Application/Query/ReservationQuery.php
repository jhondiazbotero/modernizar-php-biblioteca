<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the reservations screen. Statuses are the ones the library writes (pendiente, asignada). */
interface ReservationQuery
{
    /**
     * Pending and assigned reservations of a book, first to reserve first.
     *
     * @return array<int, array{member: string, reserved_at: string, status: string}>
     */
    public function queueOfBook(int $bookId): array;

    /**
     * Every pending and assigned reservation, by book title and then by reservation time.
     *
     * @return array<int, array{id: int, book_id: int, title: string, member: string, reserved_at: string, status: string, code: string|null}>
     */
    public function current(): array;
}
