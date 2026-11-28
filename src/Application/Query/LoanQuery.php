<?php

declare(strict_types=1);

namespace Library\Application\Query;

/**
 * Read side of the loans screens. A loan row is
 * array{id: int, member_id: int, member: string, title: string, code: string, loaned_on: string, due_date: string, returned_on: string|null, fine: float}.
 */
interface LoanQuery
{
    /**
     * Loans not returned yet, by due date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function active(): array;

    /**
     * Loans not returned whose due date is before $today, by due date.
     *
     * @return array<int, array<string, mixed>>
     */
    public function overdue(string $today): array;

    /**
     * Loans already returned, last returned first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function returned(): array;

    /**
     * Loans not returned, for the select of the return form, by book title and copy code.
     *
     * @return array<int, array{id: int, due_date: string, member: string, title: string, code: string}>
     */
    public function openForReturn(): array;
}
