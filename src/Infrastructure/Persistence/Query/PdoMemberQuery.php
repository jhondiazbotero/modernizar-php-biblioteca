<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\MemberQuery;

final class PdoMemberQuery implements MemberQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $members = [];
        foreach ($this->pdo->query('SELECT * FROM socios ORDER BY id ASC')->fetchAll() as $row) {
            $members[] = $this->member($row);
        }

        return $members;
    }

    public function find(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT * FROM socios WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch();

        return $row ? $this->member($row) : null;
    }

    public function loansOf(int $memberId): array
    {
        $statement = $this->pdo->prepare('SELECT p.*, l.titulo, e.codigo
            FROM prestamos p
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.socio_id = ?
            ORDER BY p.fecha_prestamo DESC, p.id DESC');
        $statement->execute([$memberId]);

        $loans = [];
        foreach ($statement->fetchAll() as $row) {
            $loans[] = [
                'title' => (string) $row['titulo'],
                'code' => (string) $row['codigo'],
                'loaned_on' => (string) $row['fecha_prestamo'],
                'due_date' => (string) $row['fecha_vence'],
                'returned_on' => $row['fecha_devolucion'] === null ? null : (string) $row['fecha_devolucion'],
                'fine' => (float) $row['multa'],
                'fine_paid' => (bool) $row['multa_pagada'],
            ];
        }

        return $loans;
    }

    public function optionsForLending(): array
    {
        $members = [];
        foreach ($this->pdo->query('SELECT id, nombre, documento, activo FROM socios ORDER BY nombre ASC')->fetchAll() as $row) {
            $members[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['nombre'],
                'document' => (string) $row['documento'],
                'active' => (bool) $row['activo'],
            ];
        }

        return $members;
    }

    public function activeOptions(): array
    {
        $members = [];
        foreach ($this->pdo->query('SELECT id, nombre FROM socios WHERE activo = 1 ORDER BY nombre ASC')->fetchAll() as $row) {
            $members[] = ['id' => (int) $row['id'], 'name' => (string) $row['nombre']];
        }

        return $members;
    }

    public function unpaidFineTotal(int $memberId): float
    {
        $statement = $this->pdo->prepare('SELECT COALESCE(SUM(multa), 0) FROM prestamos WHERE socio_id = ? AND multa > 0 AND multa_pagada = 0');
        $statement->execute([$memberId]);

        return (float) $statement->fetchColumn();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: int, document: string, name: string, email: string, phone: string, registered_on: string, active: bool}
     */
    private function member(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'document' => (string) $row['documento'],
            'name' => (string) $row['nombre'],
            'email' => (string) $row['correo'],
            'phone' => (string) $row['telefono'],
            'registered_on' => (string) $row['fecha_alta'],
            'active' => (bool) $row['activo'],
        ];
    }
}
