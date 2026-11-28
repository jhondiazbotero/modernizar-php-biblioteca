<?php

declare(strict_types=1);

namespace Library\Application;

final class AuthResult extends OperationResult
{
    /** @var int */
    private $librarianId = 0;

    /** @var string */
    private $librarianName = '';

    public static function success(int $librarianId, string $librarianName): self
    {
        $result = new self(null);
        $result->librarianId = $librarianId;
        $result->librarianName = $librarianName;

        return $result;
    }

    public static function failure(string $errorCode): self
    {
        return new self($errorCode);
    }

    public function getLibrarianId(): int
    {
        return $this->librarianId;
    }

    public function getLibrarianName(): string
    {
        return $this->librarianName;
    }
}
