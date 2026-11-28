<?php

declare(strict_types=1);

namespace Library\Application;

final class ReservationResult extends OperationResult
{
    /** @var int */
    private $reservationId = 0;

    /** @var int */
    private $bookId = 0;

    /** @var string */
    private $memberName = '';

    public static function success(int $reservationId, int $bookId, string $memberName): self
    {
        $result = new self(null);
        $result->reservationId = $reservationId;
        $result->bookId = $bookId;
        $result->memberName = $memberName;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    public function getReservationId(): int
    {
        return $this->reservationId;
    }

    public function getBookId(): int
    {
        return $this->bookId;
    }

    public function getMemberName(): string
    {
        return $this->memberName;
    }
}
