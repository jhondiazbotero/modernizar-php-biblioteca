<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\LoanRepository;
use Library\Domain\LoanSnapshot;

final class PdoLoanRepository implements LoanRepository
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function countActiveOfMember(int $memberId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM prestamos WHERE socio_id = ? AND fecha_devolucion IS NULL');
        $statement->execute([$memberId]);

        return (int) $statement->fetchColumn();
    }

    public function countUnpaidFinesOfMember(int $memberId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM prestamos WHERE socio_id = ? AND multa > 0 AND multa_pagada = 0');
        $statement->execute([$memberId]);

        return (int) $statement->fetchColumn();
    }

    public function add(int $copyId, int $memberId, \DateTimeImmutable $loanedOn, \DateTimeImmutable $dueDate): int
    {
        $statement = $this->pdo->prepare('INSERT INTO prestamos (ejemplar_id, socio_id, fecha_prestamo, fecha_vence, multa, multa_pagada) VALUES (?, ?, ?, ?, 0, 0)');
        $statement->execute([$copyId, $memberId, $loanedOn->format('Y-m-d'), $dueDate->format('Y-m-d')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function findForUpdate(int $id): ?LoanSnapshot
    {
        $statement = $this->pdo->prepare('SELECT p.id, p.ejemplar_id, e.libro_id, l.titulo, s.nombre AS socio, p.fecha_vence, p.fecha_devolucion, p.multa, p.multa_pagada
            FROM prestamos p
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            JOIN socios s ON s.id = p.socio_id
            WHERE p.id = ?
            FOR UPDATE OF p');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        return new LoanSnapshot(
            (int) $row['id'],
            (int) $row['ejemplar_id'],
            (int) $row['libro_id'],
            (string) $row['titulo'],
            (string) $row['socio'],
            $this->date((string) $row['fecha_vence']),
            $row['fecha_devolucion'] === null ? null : $this->date((string) $row['fecha_devolucion']),
            (int) round((float) $row['multa']),
            (bool) $row['multa_pagada']
        );
    }

    public function markReturned(int $id, \DateTimeImmutable $returnedOn, int $fine): void
    {
        $statement = $this->pdo->prepare('UPDATE prestamos SET fecha_devolucion = ?, multa = ? WHERE id = ?');
        $statement->execute([$returnedOn->format('Y-m-d'), $fine, $id]);
    }

    public function markFinePaid(int $id): void
    {
        $statement = $this->pdo->prepare('UPDATE prestamos SET multa_pagada = 1 WHERE id = ?');
        $statement->execute([$id]);
    }

    private function date(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable(substr($value, 0, 10));
    }
}
