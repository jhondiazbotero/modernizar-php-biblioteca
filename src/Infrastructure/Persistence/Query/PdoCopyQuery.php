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
