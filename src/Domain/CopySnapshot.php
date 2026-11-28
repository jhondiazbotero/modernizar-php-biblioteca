<?php

declare(strict_types=1);

namespace Library\Domain;

/** What the lending rules need to know about a copy of a book. */
final class CopySnapshot
{
    public const AVAILABLE = 'available';
    public const ON_LOAN = 'on_loan';
    public const RESERVED = 'reserved';
    public const WITHDRAWN = 'withdrawn';

    private const STATUSES = [self::AVAILABLE, self::ON_LOAN, self::RESERVED, self::WITHDRAWN];

    /** @var int */
    private $id;

    /** @var int */
    private $bookId;

    /** @var string */
    private $bookTitle;

    /** @var string */
    private $status;

    public function __construct(int $id, int $bookId, string $bookTitle, string $status)
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Unknown copy status: ' . $status);
        }
        $this->id = $id;
        $this->bookId = $bookId;
        $this->bookTitle = $bookTitle;
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

    public function getBookTitle(): string
    {
        return $this->bookTitle;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::AVAILABLE;
    }

    public function isReserved(): bool
    {
        return $this->status === self::RESERVED;
    }
}
