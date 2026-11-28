<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use DateTimeImmutable;
use DateTimeZone;
use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The fixed-date header is only honoured when it is a real date (Y-m-d or
 * Y-m-d H:i:s). Anything else is ignored and the loan gets today's real date.
 */
final class InvalidFixedDateTest extends CharacterizationTestCase
{
    /** @return array<string, array{string}> */
    public static function invalidDates(): array
    {
        return [
            'day that does not exist' => ['2026-02-30'],
            'month and day without zero padding' => ['2026-4-5'],
            'hour out of range' => ['2026-04-15 25:00:00'],
            'not a date' => ['basura'],
        ];
    }

    #[DataProvider('invalidDates')]
    public function test_an_invalid_fixed_date_is_ignored(string $header): void
    {
        $this->app->setDate($header);

        $before = $this->today();
        $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20]);
        $after = $this->today();

        $loan = $this->db->fetchOne('SELECT fecha_prestamo, fecha_vence FROM prestamos WHERE id = 21');
        self::assertContains($loan['fecha_prestamo'], [$before, $after]);
        $expectedDue = (new DateTimeImmutable($loan['fecha_prestamo']))->modify('+14 days')->format('Y-m-d');
        self::assertSame($expectedDue, $loan['fecha_vence']);
    }

    private function today(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('America/Bogota')))->format('Y-m-d');
    }
}
