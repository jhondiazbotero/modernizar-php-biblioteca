<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence;

use Library\Application\Port\ReservationRepository;
use Library\Domain\PendingReservation;
use Library\Domain\ReservationSnapshot;

final class PdoReservationRepository implements ReservationRepository
{
    /** Values of the reservas.estado column for each domain status. */
    private const DATABASE_STATUS = [
        ReservationSnapshot::PENDING => 'pendiente',
        ReservationSnapshot::ASSIGNED => 'asignada',
        ReservationSnapshot::FULFILLED => 'atendida',
        ReservationSnapshot::CANCELLED => 'cancelada',
    ];

    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findForUpdate(int $id): ?ReservationSnapshot
    {
        $statement = $this->pdo->prepare('SELECT id, libro_id, socio_id, ejemplar_id, estado FROM reservas WHERE id = ? FOR UPDATE');
        $statement->execute([$id]);
        $row = $statement->fetch();
        if (!$row) {
            return null;
        }

        $status = array_search((string) $row['estado'], self::DATABASE_STATUS, true);
        if ($status === false) {
            throw new \UnexpectedValueException('Unknown reservation status in the database: ' . $row['estado']);
        }

        return new ReservationSnapshot(
            (int) $row['id'],
            (int) $row['libro_id'],
            (int) $row['socio_id'],
            $row['ejemplar_id'] === null ? null : (int) $row['ejemplar_id'],
            (string) $status
        );
    }

    public function findAssignedId(int $copyId, int $memberId): ?int
    {
        $statement = $this->pdo->prepare("SELECT id FROM reservas WHERE ejemplar_id = ? AND socio_id = ? AND estado = 'asignada' ORDER BY id LIMIT 1 FOR UPDATE");
        $statement->execute([$copyId, $memberId]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function hasPending(int $bookId, int $memberId): bool
    {
        $statement = $this->pdo->prepare("SELECT id FROM reservas WHERE libro_id = ? AND socio_id = ? AND estado = 'pendiente'");
        $statement->execute([$bookId, $memberId]);

        return $statement->fetch() !== false;
    }

    public function pendingOfBook(int $bookId): array
    {
        $statement = $this->pdo->prepare("SELECT r.id, r.socio_id, s.nombre, r.fecha_reserva
            FROM reservas r
            JOIN socios s ON s.id = r.socio_id
            WHERE r.libro_id = ? AND r.estado = 'pendiente'
            ORDER BY r.id
            FOR UPDATE OF r");
        $statement->execute([$bookId]);

        $pending = [];
        foreach ($statement->fetchAll() as $row) {
            $pending[] = new PendingReservation(
                (int) $row['id'],
                (int) $row['socio_id'],
                (string) $row['nombre'],
                new \DateTimeImmutable((string) $row['fecha_reserva'])
            );
        }

        return $pending;
    }

    public function add(int $bookId, int $memberId, \DateTimeImmutable $reservedAt): int
    {
        $statement = $this->pdo->prepare("INSERT INTO reservas (libro_id, socio_id, fecha_reserva, estado) VALUES (?, ?, ?, 'pendiente')");
        $statement->execute([$bookId, $memberId, $reservedAt->format('Y-m-d H:i:s')]);

        return (int) $this->pdo->lastInsertId();
    }

    public function assign(int $id, int $copyId): void
    {
        $statement = $this->pdo->prepare("UPDATE reservas SET estado = 'asignada', ejemplar_id = ? WHERE id = ?");
        $statement->execute([$copyId, $id]);
    }

    public function markFulfilled(int $id): void
    {
        $statement = $this->pdo->prepare("UPDATE reservas SET estado = 'atendida' WHERE id = ?");
        $statement->execute([$id]);
    }

    public function cancel(int $id): void
    {
        $statement = $this->pdo->prepare("UPDATE reservas SET estado = 'cancelada' WHERE id = ?");
        $statement->execute([$id]);
    }
}
