<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\DashboardQuery;

final class PdoDashboardQuery implements DashboardQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function summary(string $today): array
    {
        $active = (int) $this->pdo->query('SELECT COUNT(*) FROM prestamos WHERE fecha_devolucion IS NULL')->fetchColumn();

        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM prestamos WHERE fecha_devolucion IS NULL AND fecha_vence < ?');
        $statement->execute([$today]);
        $overdueCount = (int) $statement->fetchColumn();

        $pendingCount = (int) $this->pdo->query("SELECT COUNT(*) FROM reservas WHERE estado = 'pendiente'")->fetchColumn();

        $statement = $this->pdo->prepare('SELECT p.id, p.fecha_vence, s.id AS socio_id, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.fecha_devolucion IS NULL AND p.fecha_vence < ?
            ORDER BY p.fecha_vence ASC');
        $statement->execute([$today]);
        $overdue = [];
        foreach ($statement->fetchAll() as $row) {
            $overdue[] = [
                'loan_id' => (int) $row['id'],
                'due_date' => (string) $row['fecha_vence'],
                'member_id' => (int) $row['socio_id'],
                'member' => (string) $row['socio'],
                'title' => (string) $row['titulo'],
                'code' => (string) $row['codigo'],
            ];
        }

        $rows = $this->pdo->query("SELECT r.id, r.fecha_reserva, s.nombre AS socio, l.id AS libro_id, l.titulo
            FROM reservas r
            JOIN socios s ON s.id = r.socio_id
            JOIN libros l ON l.id = r.libro_id
            WHERE r.estado = 'pendiente'
            ORDER BY r.fecha_reserva ASC")->fetchAll();
        $pending = [];
        foreach ($rows as $row) {
            $pending[] = [
                'id' => (int) $row['id'],
                'reserved_at' => (string) $row['fecha_reserva'],
                'member' => (string) $row['socio'],
                'book_id' => (int) $row['libro_id'],
                'title' => (string) $row['titulo'],
            ];
        }

        return [
            'active_loans' => $active,
            'overdue_count' => $overdueCount,
            'pending_count' => $pendingCount,
            'overdue' => $overdue,
            'pending' => $pending,
        ];
    }
}
