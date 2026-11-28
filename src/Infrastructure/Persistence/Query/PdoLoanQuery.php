<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\LoanQuery;

final class PdoLoanQuery implements LoanQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function active(): array
    {
        $statement = $this->pdo->query('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.fecha_devolucion IS NULL
            ORDER BY p.fecha_vence ASC');

        return $this->loans($statement->fetchAll());
    }

    public function overdue(string $today): array
    {
        $statement = $this->pdo->prepare('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.fecha_devolucion IS NULL AND p.fecha_vence < ?
            ORDER BY p.fecha_vence ASC');
        $statement->execute([$today]);

        return $this->loans($statement->fetchAll());
    }

    public function returned(): array
    {
        $statement = $this->pdo->query('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.fecha_devolucion IS NOT NULL
            ORDER BY p.fecha_devolucion DESC, p.id DESC');

        return $this->loans($statement->fetchAll());
    }

    public function openForReturn(): array
    {
        $rows = $this->pdo->query('SELECT p.id, p.fecha_vence, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.fecha_devolucion IS NULL
            ORDER BY l.titulo ASC, e.codigo ASC')->fetchAll();

        $loans = [];
        foreach ($rows as $row) {
            $loans[] = [
                'id' => (int) $row['id'],
                'due_date' => (string) $row['fecha_vence'],
                'member' => (string) $row['socio'],
                'title' => (string) $row['titulo'],
                'code' => (string) $row['codigo'],
            ];
        }

        return $loans;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    private function loans(array $rows): array
    {
        $loans = [];
        foreach ($rows as $row) {
            $loans[] = [
                'id' => (int) $row['id'],
                'member_id' => (int) $row['socio_id'],
                'member' => (string) $row['socio'],
                'title' => (string) $row['titulo'],
                'code' => (string) $row['codigo'],
                'loaned_on' => (string) $row['fecha_prestamo'],
                'due_date' => (string) $row['fecha_vence'],
                'returned_on' => $row['fecha_devolucion'] === null ? null : (string) $row['fecha_devolucion'],
                'fine' => (float) $row['multa'],
            ];
        }

        return $loans;
    }
}
