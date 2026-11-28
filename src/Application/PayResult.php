<?php

declare(strict_types=1);

namespace Library\Application;

final class PayResult extends OperationResult
{
    /** @var int */
    private $amount = 0;

    public static function success(int $amount): self
    {
        $result = new self(null);
        $result->amount = $amount;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    /** Pesos paid. */
    public function getAmount(): int
    {
        return $this->amount;
    }
}
