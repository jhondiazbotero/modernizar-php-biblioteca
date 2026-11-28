<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the fines screen. */
interface FineQuery
{
    /**
     * Loans with a fine, unpaid first and then the most recently returned.
     *
     * @return array<int, array{id: int, member_id: int, member: string, title: string, due_date: string, returned_on: string|null, fine: float, paid: bool}>
     */
    public function all(): array;
}
