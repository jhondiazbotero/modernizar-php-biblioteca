<?php

declare(strict_types=1);

namespace Library\Application;

final class CancelResult extends OperationResult
{
    /** @var bool */
    private $hadCopy = false;

    /** @var string|null */
    private $assignedMemberName;

    public static function success(bool $hadCopy, ?string $assignedMemberName): self
    {
        $result = new self(null);
        $result->hadCopy = $hadCopy;
        $result->assignedMemberName = $assignedMemberName;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    /** True when the cancelled reservation had a copy set aside. */
    public function hadCopy(): bool
    {
        return $this->hadCopy;
    }

    /** Member the freed copy was set aside for, or null when it went back to the shelf. */
    public function getAssignedMemberName(): ?string
    {
        return $this->assignedMemberName;
    }
}
