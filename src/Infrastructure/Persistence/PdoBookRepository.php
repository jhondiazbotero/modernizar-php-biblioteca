<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\BookRepository;

final class PdoBookRepository implements BookRepository
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function existsForUpdate(int $id): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM libros WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);

        return $statement->fetch() !== false;
    }
}
