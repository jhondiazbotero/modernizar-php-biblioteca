<?php

declare(strict_types=1);

namespace Library\Application;

/** Outcome of a use case: success, or an English error code the screen translates. */
abstract class OperationResult
{
    /** @var string|null */
    private $errorCode;

    protected function __construct(?string $errorCode)
    {
        $this->errorCode = $errorCode;
    }

    public function isSuccess(): bool
    {
        return $this->errorCode === null;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }
}
