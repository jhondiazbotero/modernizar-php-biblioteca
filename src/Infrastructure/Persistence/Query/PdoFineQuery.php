<?php

declare(strict_types=1);

namespace Library\Infrastructure\Persistence\Query;

use Library\Application\Query\FineQuery;

final class PdoFineQuery implements FineQuery
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function all(): array
    {
        $rows = $this->pdo->query('SELECT p.*, s.nombre AS socio, l.titulo, e.codigo
            FROM prestamos p
            JOIN socios s ON s.id = p.socio_id
            JOIN ejemplares e ON e.id = p.ejemplar_id
            JOIN libros l ON l.id = e.libro_id
            WHERE p.multa > 0
            ORDER BY p.multa_pagada ASC, p.fecha_devolucion DESC')->fetchAll();

        $fines = [];
        foreach ($rows as $row) {
            $fines[] = [
                'id' => (int) $row['id'],
                'member_id' => (int) $row['socio_id'],
                'member' => (string) $row['socio'],
                'title' => (string) $row['titulo'],
                'due_date' => (string) $row['fecha_vence'],
                'returned_on' => $row['fecha_devolucion'] === null ? null : (string) $row['fecha_devolucion'],
                'fine' => (float) $row['multa'],
                'paid' => (bool) $row['multa_pagada'],
            ];
        }

        return $fines;
    }
}
