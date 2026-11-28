<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\CopyQuery;

final class PdoCopyQuery implements CopyQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function forLending(): array
    {
        $rows = $this->pdo->query("SELECT e.id, e.codigo, e.estado, l.titulo
            FROM ejemplares e
            JOIN libros l ON l.id = e.libro_id
            WHERE e.estado <> 'baja'
            ORDER BY l.titulo ASC, e.codigo ASC")->fetchAll();

        $copies = [];
        foreach ($rows as $row) {
            $copies[] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['codigo'],
                'status' => (string) $row['estado'],
                'title' => (string) $row['titulo'],
            ];
        }

        return $copies;
    }

    public function ofBook(int $bookId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM ejemplares WHERE libro_id = ? ORDER BY codigo ASC');
        $statement->execute([$bookId]);

        $copies = [];
        foreach ($statement->fetchAll() as $row) {
            $copies[] = ['id' => (int) $row['id'], 'code' => (string) $row['codigo'], 'status' => (string) $row['estado']];
        }

        return $copies;
    }
}
