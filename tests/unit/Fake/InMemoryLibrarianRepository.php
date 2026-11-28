<?php

declare(strict_types=1);

namespace Library\Tests\Fake;

use Library\Application\Port\LibrarianRepository;
use Library\Domain\LibrarianAccount;

final class InMemoryLibrarianRepository implements LibrarianRepository
{
    /** @var array<string, LibrarianAccount> by user name */
    public $accounts = [];

    /** @var array<int, string> ids whose password was rewritten, in order */
    public $rewritten = [];

    public function add(string $username, LibrarianAccount $account): void
    {
        $this->accounts[$username] = $account;
    }

    public function findByUsername(string $username): ?LibrarianAccount
    {
        return $this->accounts[$username] ?? null;
    }

    public function updatePassword(int $id, string $storedPassword): void
    {
        $this->rewritten[] = (string) $id;
        foreach ($this->accounts as $username => $account) {
            if ($account->getId() === $id) {
                $this->accounts[$username] = new LibrarianAccount($id, $account->getName(), $storedPassword);
            }
        }
    }
}
