<?php

declare(strict_types=1);

namespace Library\Tests\Integration;

use Library\Tests\Integration\Support\DatabaseTestCase;

/** db/consultas/multas-cruce-de-mes.sql lists the returned loans whose stored fine is not the right one. */
final class FineReviewQueryTest extends DatabaseTestCase
{
    private const QUERY = __DIR__ . '/../../db/consultas/multas-cruce-de-mes.sql';

    private function loadSeed(): void
    {
        foreach (['reservas', 'prestamos', 'ejemplares', 'libros', 'socios'] as $table) {
            $this->pdo->exec('TRUNCATE TABLE ' . $table);
        }
        $this->pdo->exec((string) file_get_contents(__DIR__ . '/../../db/02-semilla.sql'));
    }

    /** @return array<int, array<string, mixed>> */
    private function review(): array
    {
        return $this->observer->query((string) file_get_contents(self::QUERY))->fetchAll();
    }

    /** @return string[] */
    private function checksums(): array
    {
        $sums = [];
        foreach (['socios', 'libros', 'ejemplares', 'prestamos', 'reservas'] as $table) {
            $sums[$table] = (string) $this->observer->query('CHECKSUM TABLE ' . $table)->fetch(\PDO::FETCH_ASSOC)['Checksum'];
        }

        return $sums;
    }

    public function test_the_seed_lists_only_the_loan_that_crossed_a_month_without_fine(): void
    {
        $this->loadSeed();

        $rows = $this->review();

        self::assertCount(1, $rows);
        self::assertSame(
            ['prestamo_id', 'socio', 'vencimiento', 'devolucion', 'multa_guardada', 'multa_correcta'],
            array_keys($rows[0])
        );
        self::assertSame('12', (string) $rows[0]['prestamo_id']);
        self::assertSame('Andrés Ramírez Soto', $rows[0]['socio']);
        self::assertSame('2026-03-28', $rows[0]['vencimiento']);
        self::assertSame('2026-04-03', $rows[0]['devolucion']);
        self::assertEquals(0, $rows[0]['multa_guardada']);
        self::assertEquals(3000, $rows[0]['multa_correcta']);
    }

    public function test_a_loan_with_the_right_fine_is_not_listed(): void
    {
        $this->loadSeed();
        $this->pdo->exec('UPDATE prestamos SET multa = 3000 WHERE id = 12');

        self::assertSame([], $this->review());
    }

    public function test_a_fine_charged_with_the_old_rule_in_the_next_month_is_listed_with_the_right_amount(): void
    {
        $this->loadSeed();
        $this->pdo->exec('UPDATE prestamos SET multa = 3000 WHERE id = 12');
        // due 9 November, returned 20 December: 41 days late, charged 11 days
        $this->pdo->exec("INSERT INTO prestamos (id, ejemplar_id, socio_id, fecha_prestamo, fecha_vence, fecha_devolucion, multa, multa_pagada)
            VALUES (30, 5, 4, '2026-10-26', '2026-11-09', '2026-12-20', 5500, 1)");

        $rows = $this->review();

        self::assertCount(1, $rows);
        self::assertSame('30', (string) $rows[0]['prestamo_id']);
        self::assertEquals(5500, $rows[0]['multa_guardada']);
        self::assertEquals(20500, $rows[0]['multa_correcta']);
    }

    public function test_the_query_only_reads(): void
    {
        $this->loadSeed();
        $before = $this->checksums();

        $this->review();
        $this->review();

        self::assertSame($before, $this->checksums());
        self::assertSame(
            0,
            preg_match('/\b(insert|update|delete|replace|truncate|alter|drop|create|grant)\b/i', (string) file_get_contents(self::QUERY))
        );
    }
}
