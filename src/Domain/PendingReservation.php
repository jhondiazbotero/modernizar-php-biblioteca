<?php

declare(strict_types=1);

namespace Library\Domain;

/** A reservation waiting for a copy of a book. */
final class PendingReservation
{
    /** @var int */
    private $id;

    /** @var int */
    private $memberId;

    /** @var string */
    private $memberName;

    /** @var \DateTimeImmutable */
    private $reservedAt;

    public function __construct(int $id, int $memberId, string $memberName, \DateTimeImmutable $reservedAt)
    {
        $this->id = $id;
        $this->memberId = $memberId;
        $this->memberName = $memberName;
        $this->reservedAt = $reservedAt;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getMemberName(): string
    {
        return $this->memberName;
    }

    public function getReservedAt(): \DateTimeImmutable
    {
        return $this->reservedAt;
    }
}
