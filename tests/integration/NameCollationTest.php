<?php

declare(strict_types=1);

namespace Library\Tests\Integration;

use Library\Domain\NameCollation;
use Library\Tests\Integration\Support\DatabaseTestCase;

/** The order of names in the domain must be the one MySQL gives with utf8mb4_unicode_ci. */
final class NameCollationTest extends DatabaseTestCase
{
    public function test_the_domain_order_matches_the_database_order(): void
    {
        $names = [
            'Álvaro', 'Andrés', 'andrés', 'ANDRÉS', 'Ana', 'Ana ', 'Ana María', 'Ángela', 'Beatriz', 'Çelik', 'Celia',
            'Ève', 'Eva', 'José', 'Jose', 'Muñoz', 'Munera', 'Murillo', 'Ñandú', 'Nora', 'Nadia', 'Óscar', 'Oscar',
            'Úrsula', 'Ursula', 'Ümit', 'Umberto', 'Zoe', 'zoe', 'Zoé', 'Ana-Lucía', 'Ana Lucía', 'Ana2', 'Ana10',
        ];

        $this->pdo->exec('TRUNCATE TABLE socios');
        $insert = $this->pdo->prepare("INSERT INTO socios (id, documento, nombre, fecha_alta, activo) VALUES (?, ?, ?, '2026-01-01', 1)");
        foreach ($names as $index => $name) {
            $insert->execute([$index + 1, 'doc' . $index, $name]);
        }

        $fromDatabase = array_map('intval', $this->observer->query('SELECT id FROM socios ORDER BY nombre ASC, id ASC')->fetchAll(\PDO::FETCH_COLUMN));

        $ids = range(1, count($names));
        usort($ids, function (int $a, int $b) use ($names): int {
            $byName = NameCollation::compare($names[$a - 1], $names[$b - 1]);

            return $byName !== 0 ? $byName : $a <=> $b;
        });

        self::assertSame($fromDatabase, $ids);
    }
}
