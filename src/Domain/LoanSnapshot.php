<?php

declare(strict_types=1);

namespace Library\Domain;

/** A loan as the use cases need to see it. Money is in whole pesos. */
final class LoanSnapshot
{
    /** @var int */
    private $id;

    /** @var int */
    private $copyId;

    /** @var int */
    private $bookId;

    /** @var string */
    private $bookTitle;

    /** @var string */
    private $memberName;

    /** @var \DateTimeImmutable */
    private $dueDate;

    /** @var \DateTimeImmutable|null */
    private $returnedOn;

    /** @var int */
    private $fine;

    /** @var bool */
    private $finePaid;

    public function __construct(
        int $id,
        int $copyId,
        int $bookId,
        string $bookTitle,
        string $memberName,
        \DateTimeImmutable $dueDate,
        ?\DateTimeImmutable $returnedOn,
        int $fine,
        bool $finePaid
    ) {
        $this->id = $id;
        $this->copyId = $copyId;
        $this->bookId = $bookId;
        $this->bookTitle = $bookTitle;
        $this->memberName = $memberName;
        $this->dueDate = $dueDate;
        $this->returnedOn = $returnedOn;
        $this->fine = $fine;
        $this->finePaid = $finePaid;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCopyId(): int
    {
        return $this->copyId;
    }

    public function getBookId(): int
    {
        return $this->bookId;
    }

    public function getBookTitle(): string
    {
        return $this->bookTitle;
    }

    public function getMemberName(): string
    {
        return $this->memberName;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getReturnedOn(): ?\DateTimeImmutable
    {
        return $this->returnedOn;
    }

    public function getFine(): int
    {
        return $this->fine;
    }

    public function isFinePaid(): bool
    {
        return $this->finePaid;
    }

    public function isReturned(): bool
    {
        return $this->returnedOn !== null;
    }
}
