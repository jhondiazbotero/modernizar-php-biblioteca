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

    public function add(string $isbn, string $title, string $author, ?int $year): int
    {
        $statement = $this->pdo->prepare('INSERT INTO libros (isbn, titulo, autor, anio) VALUES (?, ?, ?, ?)');
        $statement->execute([$isbn, $title, $author, $year]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $isbn, string $title, string $author, ?int $year): void
    {
        $statement = $this->pdo->prepare('UPDATE libros SET isbn = ?, titulo = ?, autor = ?, anio = ? WHERE id = ?');
        $statement->execute([$isbn, $title, $author, $year, $id]);
    }
}
