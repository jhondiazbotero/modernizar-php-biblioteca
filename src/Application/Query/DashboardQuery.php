<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the dashboard. */
interface DashboardQuery
{
    /**
     * @return array{
     *     active_loans: int,
     *     overdue_count: int,
     *     pending_count: int,
     *     overdue: array<int, array{loan_id: int, due_date: string, member_id: int, member: string, title: string, code: string}>,
     *     pending: array<int, array{id: int, reserved_at: string, member: string, book_id: int, title: string}>
     * }
     */
    public function summary(string $today): array;
}
