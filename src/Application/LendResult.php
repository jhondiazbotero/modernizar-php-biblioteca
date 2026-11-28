<?php

declare(strict_types=1);

namespace Library\Application;

final class LendResult extends OperationResult
{
    /** @var int */
    private $loanId = 0;

    /** @var string */
    private $bookTitle = '';

    /** @var string */
    private $memberName = '';

    /** @var \DateTimeImmutable|null */
    private $dueDate;

    public static function success(int $loanId, string $bookTitle, string $memberName, \DateTimeImmutable $dueDate): self
    {
        $result = new self(null);
        $result->loanId = $loanId;
        $result->bookTitle = $bookTitle;
        $result->memberName = $memberName;
        $result->dueDate = $dueDate;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    public function getLoanId(): int
    {
        return $this->loanId;
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
        if ($this->dueDate === null) {
            throw new \LogicException('A failed lending has no due date.');
        }

        return $this->dueDate;
    }
}
