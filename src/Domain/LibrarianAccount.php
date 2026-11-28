<?php

declare(strict_types=1);

namespace Library\Domain;

/** A librarian who can sign in, with the stored form of the password (an old MD5 or a password_hash value). */
final class LibrarianAccount
{
    /** @var int */
    private $id;

    /** @var string */
    private $name;

    /** @var string */
    private $storedPassword;

    public function __construct(int $id, string $name, string $storedPassword)
    {
        $this->id = $id;
        $this->name = $name;
        $this->storedPassword = $storedPassword;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStoredPassword(): string
    {
        return $this->storedPassword;
    }
}
