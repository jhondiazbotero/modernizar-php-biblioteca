<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\BookQuery;

final class PdoBookQuery implements BookQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function allWithCopyCounts(): array
    {
        $rows = $this->pdo->query("SELECT l.*,
                COUNT(CASE WHEN e.estado <> 'baja' THEN 1 END) AS total,
                COUNT(CASE WHEN e.estado = 'disponible' THEN 1 END) AS disponibles
            FROM libros l
            LEFT JOIN ejemplares e ON e.libro_id = l.id
            GROUP BY l.id
            ORDER BY l.titulo ASC")->fetchAll();

        $books = [];
        foreach ($rows as $row) {
            $books[] = $this->book($row) + ['total' => (int) $row['total'], 'available' => (int) $row['disponibles']];
        }

        return $books;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM libros WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row ? $this->book($row) : null;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, isbn: string, title: string, author: string, year: int|null}
     */
    private function book(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'isbn' => (string) $row['isbn'],
            'title' => (string) $row['titulo'],
            'author' => (string) $row['autor'],
            'year' => $row['anio'] === null ? null : (int) $row['anio'],
        ];
    }
}
