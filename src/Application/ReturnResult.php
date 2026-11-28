<?php

declare(strict_types=1);

namespace Library\Application;

final class ReturnResult extends OperationResult
{
    /** @var string */
    private $bookTitle = '';

    /** @var string */
    private $memberName = '';

    /** @var int */
    private $fine = 0;

    /** @var string|null */
    private $assignedMemberName;

    public static function success(string $bookTitle, string $memberName, int $fine, ?string $assignedMemberName): self
    {
        $result = new self(null);
        $result->bookTitle = $bookTitle;
        $result->memberName = $memberName;
        $result->fine = $fine;
        $result->assignedMemberName = $assignedMemberName;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    public function getBookTitle(): string
    {
        return $this->bookTitle;
    }

    public function getMemberName(): string
    {
        return $this->memberName;
    }

    /** Pesos of fine, 0 when none. */
    public function getFine(): int
    {
        return $this->fine;
    }

    /** Member the copy was set aside for, or null when it went back to the shelf. */
    public function getAssignedMemberName(): ?string
    {
        return $this->assignedMemberName;
    }
}
