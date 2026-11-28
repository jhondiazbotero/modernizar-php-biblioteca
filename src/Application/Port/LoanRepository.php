<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\LoanSnapshot;

interface LoanRepository
{
    public function countActiveOfMember(int $memberId): int;

    /** Loans of the member with a fine that is not paid yet. */
    public function countUnpaidFinesOfMember(int $memberId): int;

    /** Registers a loan without fine and returns its id. */
    public function add(int $copyId, int $memberId, \DateTimeImmutable $loanedOn, \DateTimeImmutable $dueDate): int;

    /** Reads the loan and locks it until the end of the transaction. */
    public function findForUpdate(int $id): ?LoanSnapshot;

    public function markReturned(int $id, \DateTimeImmutable $returnedOn, int $fine): void;

    public function markFinePaid(int $id): void;
}
