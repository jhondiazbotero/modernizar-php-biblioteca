<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Query\ReservationQuery;

final class FakeReservationQuery implements ReservationQuery
{
    /** @var array<int, array<int, array{member: string, reserved_at: string, status: string}>> by book id */
    public $queues = [];

    /** @var array<int, array{id: int, book_id: int, title: string, member: string, reserved_at: string, status: string, code: string|null}> */
    public $current = [];

    /** @var array<int, int> book ids whose queue was asked for */
    public $queuesAsked = [];

    public function queueOfBook(int $bookId): array
    {
        $this->queuesAsked[] = $bookId;

        return $this->queues[$bookId] ?? [];
    }

    public function current(): array
    {
        return $this->current;
    }
}
