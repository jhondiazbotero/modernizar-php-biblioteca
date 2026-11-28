<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\CopyRepository;
use Library\Domain\CopySnapshot;

final class PdoCopyRepository implements CopyRepository
{
    /** Values of the ejemplares.estado column for each domain status. */
    private const DATABASE_STATUS = [
        CopySnapshot::AVAILABLE => 'disponible',
        CopySnapshot::ON_LOAN => 'prestado',
        CopySnapshot::RESERVED => 'reservado',
        CopySnapshot::WITHDRAWN => 'baja',
    ];

    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findForUpdate(int $id): ?CopySnapshot
    {
        $statement = $this->pdo->prepare('SELECT e.id, e.libro_id, l.titulo, e.estado FROM ejemplares e JOIN libros l ON l.id = e.libro_id WHERE e.id = ? FOR UPDATE OF e');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        $status = array_search((string) $row['estado'], self::DATABASE_STATUS, true);
        if ($status === false) {
            throw new \UnexpectedValueException('Unknown copy status in the database: ' . $row['estado']);
        }

        return new CopySnapshot((int) $row['id'], (int) $row['libro_id'], (string) $row['titulo'], (string) $status);
    }

    public function countAvailableOfBook(int $bookId): int
    {
        $statement = $this->pdo->prepare("SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'disponible'");
        $statement->execute([$bookId]);

        return (int) $statement->fetchColumn();
    }

    public function codeTaken(string $code): bool
    {
        $statement = $this->pdo->prepare('SELECT id FROM ejemplares WHERE codigo = ?');
        $statement->execute([$code]);

        return $statement->fetch() !== false;
    }

    public function add(int $bookId, string $code): int
    {
        $statement = $this->pdo->prepare("INSERT INTO ejemplares (libro_id, codigo, estado) VALUES (?, ?, 'disponible')");
        $statement->execute([$bookId, $code]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateStatus(int $copyId, string $status): void
    {
        if (!isset(self::DATABASE_STATUS[$status])) {
            throw new \InvalidArgumentException('Unknown copy status: ' . $status);
        }
        $statement = $this->pdo->prepare('UPDATE ejemplares SET estado = ? WHERE id = ?');
        $statement->execute([self::DATABASE_STATUS[$status], $copyId]);
    }
}
