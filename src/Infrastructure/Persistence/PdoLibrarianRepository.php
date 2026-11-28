<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\LibrarianRepository;
use Library\Domain\LibrarianAccount;

final class PdoLibrarianRepository implements LibrarianRepository
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUsername(string $username): ?LibrarianAccount
    {
        $statement = $this->pdo->prepare('SELECT id, nombre, clave FROM bibliotecarios WHERE usuario = ?');
        $statement->execute([$username]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        return new LibrarianAccount((int) $row['id'], (string) $row['nombre'], (string) $row['clave']);
    }

    public function updatePassword(int $id, string $storedPassword): void
    {
        $statement = $this->pdo->prepare('UPDATE bibliotecarios SET clave = ? WHERE id = ?');
        $statement->execute([$storedPassword, $id]);
    }
}
