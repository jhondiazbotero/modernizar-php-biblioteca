<?php

declare(strict_types=1);

namespace Library\Application\Query;

/** Read side of the members screens. */
interface MemberQuery
{
    /**
     * Every member, oldest first.
     *
     * @return array<int, array{id: int, document: string, name: string, email: string, phone: string, registered_on: string, active: bool}>
     */
    public function all(): array;

    /** @return array{id: int, document: string, name: string, email: string, phone: string, registered_on: string, active: bool}|null */
    public function find(int $id): ?array;

    /**
     * Loans of a member, newest first.
     *
     * @return array<int, array{title: string, code: string, loaned_on: string, due_date: string, returned_on: string|null, fine: float, fine_paid: bool}>
     */
    public function loansOf(int $memberId): array;

    /** Pesos of fines the member has not paid yet. */
    public function unpaidFineTotal(int $memberId): float;
}
