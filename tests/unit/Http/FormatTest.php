<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Format;
use PHPUnit\Framework\TestCase;

final class FormatTest extends TestCase
{
    /** @return array<string, array{mixed, string}> */
    public function moneyCases(): array
    {
        return [
            'zero' => [0, '$ 0'],
            'hundreds' => [500, '$ 500'],
            'thousands use a dot' => [1500, '$ 1.500'],
            'millions' => [1234567, '$ 1.234.567'],
            'decimal column as a string' => ['6500.00', '$ 6.500'],
            'decimal string with cents rounds' => ['6500.50', '$ 6.501'],
            'integer string' => ['13000', '$ 13.000'],
            'float' => [3000.0, '$ 3.000'],
        ];
    }

    /**
     * @dataProvider moneyCases
     *
     * @param mixed $value
     */
    public function test_money_is_shown_in_colombian_pesos($value, string $expected): void
    {
        self::assertSame($expected, Format::money($value));
    }

    /** @return array<string, array{mixed, string}> */
    public function dateCases(): array
    {
        return [
            'a date' => ['2026-03-28', '28/03/2026'],
            'first day' => ['2026-01-01', '01/01/2026'],
            'leap day' => ['2028-02-29', '29/02/2028'],
            'a date with time' => ['2026-03-28 18:30:00', '28/03/2026'],
            'null' => [null, '-'],
            'empty string' => ['', '-'],
        ];
    }

    /**
     * @dataProvider dateCases
     *
     * @param mixed $value
     */
    public function test_date_is_day_month_year_and_a_dash_when_empty($value, string $expected): void
    {
        self::assertSame($expected, Format::date($value));
    }

    public function test_date_time_adds_hours_and_minutes(): void
    {
        self::assertSame('27/11/2026 09:05', Format::dateTime('2026-11-27 09:05:59'));
        self::assertSame('01/04/2026 23:59', Format::dateTime('2026-04-01 23:59:00'));
    }

    public function test_member_status_is_active_or_inactive(): void
    {
        self::assertSame('Activo', Format::memberStatus(true));
        self::assertSame('Activo', Format::memberStatus(1));
        self::assertSame('Activo', Format::memberStatus('1'));
        self::assertSame('Inactivo', Format::memberStatus(false));
        self::assertSame('Inactivo', Format::memberStatus(0));
        self::assertSame('Inactivo', Format::memberStatus('0'));
    }
}
