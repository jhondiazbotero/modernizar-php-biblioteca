<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Domain\CopySnapshot;
use Library\Domain\MemberSnapshot;
use Library\Domain\ReservationSnapshot;

/**
 * The "database" shared by the in-memory repositories. Every write must happen
 * inside a transaction and can be told to fail at a given write.
 */
final class InMemoryStore
{
    /** @var MemberSnapshot[] */
    public $members = [];

    /** @var array<int, bool> */
    public $books = [];

    /** @var CopySnapshot[] */
    public $copies = [];

    /** @var array<int, array<string, mixed>> */
    public $loans = [];

    /** @var array<int, array<string, mixed>> */
    public $reservations = [];

    /** @var int */
    public $nextLoanId = 1;

    /** @var int */
    public $nextReservationId = 1;

    /** @var bool */
    public $inTransaction = false;

    /** @var string[] writes done, in order */
    public $writes = [];

    /** @var string|null */
    private $failOn;

    public function addMember(int $id, string $name, bool $active = true): void
    {
        $this->members[$id] = new MemberSnapshot($id, $name, $active);
    }

    public function addBook(int $id): void
    {
        $this->books[$id] = true;
    }

    public function addCopy(int $id, int $bookId, string $title, string $status = CopySnapshot::AVAILABLE): void
    {
        $this->books[$bookId] = true;
        $this->copies[$id] = new CopySnapshot($id, $bookId, $title, $status);
    }

    public function addLoan(int $id, int $copyId, int $memberId, string $dueDate, ?string $returnedOn = null, int $fine = 0, bool $paid = false): void
    {
        $this->loans[$id] = [
            'copyId' => $copyId,
            'memberId' => $memberId,
            'loanedOn' => new \DateTimeImmutable('2026-01-01'),
            'dueDate' => new \DateTimeImmutable($dueDate),
            'returnedOn' => $returnedOn === null ? null : new \DateTimeImmutable($returnedOn),
            'fine' => $fine,
            'paid' => $paid,
        ];
        $this->nextLoanId = max($this->nextLoanId, $id + 1);
    }

    public function addReservation(int $id, int $bookId, int $memberId, string $status, ?int $copyId = null, string $reservedAt = '2026-11-01 10:00:00'): void
    {
        $this->reservations[$id] = [
            'bookId' => $bookId,
            'memberId' => $memberId,
            'reservedAt' => new \DateTimeImmutable($reservedAt),
            'copyId' => $copyId,
            'status' => $status,
        ];
        $this->nextReservationId = max($this->nextReservationId, $id + 1);
    }

    public function copyStatus(int $id): string
    {
        return $this->copies[$id]->getStatus();
    }

    /** The next write whose name is $operation throws. */
    public function failOn(string $operation): void
    {
        $this->failOn = $operation;
    }

    /** Called by every write: it must be inside a transaction. */
    public function write(string $operation): void
    {
        if (!$this->inTransaction) {
            throw new \LogicException('Write outside a transaction: ' . $operation);
        }
        if ($this->failOn === $operation) {
            throw new \RuntimeException('Forced failure in ' . $operation);
        }
        $this->writes[] = $operation;
    }

    /** @return array<string, mixed> */
    public function state(): array
    {
        return [
            $this->members, $this->books, $this->copies, $this->loans, $this->reservations,
            $this->nextLoanId, $this->nextReservationId, $this->writes,
        ];
    }

    /** @param array<string, mixed> $state */
    public function restore(array $state): void
    {
        [$this->members, $this->books, $this->copies, $this->loans, $this->reservations,
            $this->nextLoanId, $this->nextReservationId, $this->writes] = $state;
    }
}
