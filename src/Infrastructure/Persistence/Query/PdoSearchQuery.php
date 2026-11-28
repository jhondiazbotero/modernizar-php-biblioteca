<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\SearchQuery;

final class PdoSearchQuery implements SearchQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function booksMatching(string $text): array
    {
        // The text is searched as it is: %, _ and \ get a backslash in front so they are not wildcards.
        $pattern = '%' . addcslashes($text, '%_\\') . '%';
        $statement = $this->pdo->prepare("SELECT l.*, (SELECT COUNT(*) FROM ejemplares e WHERE e.libro_id = l.id AND e.estado = 'disponible') AS disponibles FROM libros l WHERE titulo LIKE ? ESCAPE '\\\\' OR autor LIKE ? ESCAPE '\\\\' ORDER BY titulo ASC");
        $statement->execute([$pattern, $pattern]);

        $books = [];
        foreach ($statement->fetchAll() as $row) {
            $books[] = [
                'id' => (int) $row['id'],
                'title' => (string) $row['titulo'],
                'author' => (string) $row['autor'],
                'year' => $row['anio'] === null ? null : (int) $row['anio'],
                'available' => (int) $row['disponibles'],
            ];
        }

        return $books;
    }
}
