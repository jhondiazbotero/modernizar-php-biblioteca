<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\ReservationQuery;

final class PdoReservationQuery implements ReservationQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function queueOfBook(int $bookId): array
    {
        $statement = $this->pdo->prepare("SELECT r.*, s.nombre AS socio
            FROM reservas r
            JOIN socios s ON s.id = r.socio_id
            WHERE r.libro_id = ? AND r.estado IN ('pendiente', 'asignada')
            ORDER BY r.fecha_reserva ASC");
        $statement->execute([$bookId]);

        $queue = [];
        foreach ($statement->fetchAll() as $row) {
            $queue[] = [
                'member' => (string) $row['socio'],
                'reserved_at' => (string) $row['fecha_reserva'],
                'status' => (string) $row['estado'],
            ];
        }

        return $queue;
    }

    public function current(): array
    {
        $rows = $this->pdo->query("SELECT r.*, s.nombre AS socio, l.titulo, e.codigo
            FROM reservas r
            JOIN socios s ON s.id = r.socio_id
            JOIN libros l ON l.id = r.libro_id
            LEFT JOIN ejemplares e ON e.id = r.ejemplar_id
            WHERE r.estado IN ('pendiente', 'asignada')
            ORDER BY l.titulo ASC, r.fecha_reserva ASC")->fetchAll();

        $reservations = [];
        foreach ($rows as $row) {
            $reservations[] = [
                'id' => (int) $row['id'],
                'book_id' => (int) $row['libro_id'],
                'title' => (string) $row['titulo'],
                'member' => (string) $row['socio'],
                'reserved_at' => (string) $row['fecha_reserva'],
                'status' => (string) $row['estado'],
                'code' => $row['codigo'] === null ? null : (string) $row['codigo'],
            ];
        }

        return $reservations;
    }
}
