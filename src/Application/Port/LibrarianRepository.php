<?php

declare(strict_types=1);

namespace Library\Application\Port;

use Library\Domain\LibrarianAccount;

interface LibrarianRepository
{
    public function findByUsername(string $username): ?LibrarianAccount;

    public function updatePassword(int $id, string $storedPassword): void;
}
