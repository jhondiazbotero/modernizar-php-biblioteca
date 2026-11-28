<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\LoanRepository;
use Library\Domain\LoanSnapshot;

final class InMemoryLoanRepository implements LoanRepository
{
    /** @var InMemoryStore */
    private $store;

    public function __construct(InMemoryStore $store)
    {
        $this->store = $store;
    }

    public function countActiveOfMember(int $memberId): int
    {
        $count = 0;
        foreach ($this->store->loans as $loan) {
            if ($loan['memberId'] === $memberId && $loan['returnedOn'] === null) {
                $count++;
            }
        }

        return $count;
    }

    public function countUnpaidFinesOfMember(int $memberId): int
    {
        $count = 0;
        foreach ($this->store->loans as $loan) {
            if ($loan['memberId'] === $memberId && $loan['fine'] > 0 && !$loan['paid']) {
                $count++;
            }
        }

        return $count;
    }

    public function add(int $copyId, int $memberId, \DateTimeImmutable $loanedOn, \DateTimeImmutable $dueDate): int
    {
        $this->store->write('loans.add');
        $id = $this->store->nextLoanId++;
        $this->store->loans[$id] = [
            'copyId' => $copyId,
            'memberId' => $memberId,
            'loanedOn' => $loanedOn,
            'dueDate' => $dueDate,
            'returnedOn' => null,
            'fine' => 0,
            'paid' => false,
        ];

        return $id;
    }

    public function findForUpdate(int $id): ?LoanSnapshot
    {
        if (!isset($this->store->loans[$id])) {
            return null;
        }
        $loan = $this->store->loans[$id];
        $copy = $this->store->copies[$loan['copyId']];

        return new LoanSnapshot(
            $id,
            $loan['copyId'],
            $copy->getBookId(),
            $copy->getBookTitle(),
            $this->store->members[$loan['memberId']]->getName(),
            $loan['dueDate'],
            $loan['returnedOn'],
            $loan['fine'],
            $loan['paid']
        );
    }

    public function markReturned(int $id, \DateTimeImmutable $returnedOn, int $fine): void
    {
        $this->store->write('loans.markReturned');
        $this->store->loans[$id]['returnedOn'] = $returnedOn;
        $this->store->loans[$id]['fine'] = $fine;
    }

    public function markFinePaid(int $id): void
    {
        $this->store->write('loans.markFinePaid');
        $this->store->loans[$id]['paid'] = true;
    }
}
