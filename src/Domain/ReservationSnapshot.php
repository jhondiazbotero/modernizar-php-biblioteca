<?php

declare(strict_types=1);

namespace Library\Domain;

/** A reservation in any state, as the use cases need to see it. */
final class ReservationSnapshot
{
    public const PENDING = 'pending';
    public const ASSIGNED = 'assigned';
    public const FULFILLED = 'fulfilled';
    public const CANCELLED = 'cancelled';

    private const STATUSES = [self::PENDING, self::ASSIGNED, self::FULFILLED, self::CANCELLED];

    /** @var int */
    private $id;

    /** @var int */
    private $bookId;

    /** @var int */
    private $memberId;

    /** @var int|null */
    private $copyId;

    /** @var string */
    private $status;

    public function __construct(int $id, int $bookId, int $memberId, ?int $copyId, string $status)
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown reservation status: ' . $status);
        }
        $this->id = $id;
        $this->bookId = $bookId;
        $this->memberId = $memberId;
        $this->copyId = $copyId;
        $this->status = $status;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getBookId(): int
    {
        return $this->bookId;
    }

    public function getMemberId(): int
    {
        return $this->memberId;
    }

    public function getCopyId(): ?int
    {
        return $this->copyId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAssigned(): bool
    {
        return $this->status === self::ASSIGNED;
    }

    /** Only pending and assigned reservations can be cancelled. */
    public function canBeCancelled(): bool
    {
        return $this->status === self::PENDING || $this->status === self::ASSIGNED;
    }
}
