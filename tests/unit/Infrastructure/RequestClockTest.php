<?php

declare(strict_types=1);

namespace Library\Tests\Infrastructure;

use Library\Infrastructure\Clock\RequestClock;
use PHPUnit\Framework\TestCase;

final class RequestClockTest extends TestCase
{
    private function clock(?string $environment, ?string $header): RequestClock
    {
        return new RequestClock($environment, $header === null ? [] : ['X-Fecha-Fija' => $header]);
    }

    public function test_a_date_alone_fixes_today_and_puts_now_at_noon(): void
    {
        $clock = $this->clock('test', '2026-04-15');

        self::assertSame('2026-04-15 00:00:00', $clock->today()->format('Y-m-d H:i:s'));
        self::assertSame('2026-04-15 12:00:00', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function test_a_date_and_time_fix_both(): void
    {
        $clock = $this->clock('test', '2026-12-31 23:59:58');

        self::assertSame('2026-12-31 00:00:00', $clock->today()->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-31 23:59:58', $clock->now()->format('Y-m-d H:i:s'));
    }

    public function test_a_leap_day_is_valid(): void
    {
        self::assertSame('2028-02-29', $this->clock('test', '2028-02-29')->today()->format('Y-m-d'));
    }

    /** @dataProvider invalidHeaders */
    public function test_an_invalid_value_is_ignored(string $header): void
    {
        $today = $this->clock('test', $header)->today();

        self::assertSame($this->realToday(), $today->format('Y-m-d'));
    }

    public function invalidHeaders(): array
    {
        return [
            'day that does not exist' => ['2026-02-30'],
            'month and day without zero' => ['2026-4-5'],
            'hour out of range' => ['2026-04-15 25:00:00'],
            'text' => ['basura'],
            'empty' => [''],
            'zero' => ['0'],
            'date with trailing text' => ['2026-04-15 x'],
        ];
    }

    /** @dataProvider otherEnvironments */
    public function test_the_header_is_ignored_outside_the_test_environment(?string $environment): void
    {
        $clock = $this->clock($environment, '2000-01-01');

        self::assertSame($this->realToday(), $clock->today()->format('Y-m-d'));
    }

    public function otherEnvironments(): array
    {
        return [['production'], ['TEST'], [''], [null]];
    }

    public function test_without_a_header_it_returns_the_real_time_in_bogota(): void
    {
        $clock = $this->clock('test', null);
        $before = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));
        $now = $clock->now();
        $after = new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota'));

        self::assertSame('America/Bogota', $now->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $now->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $now->getTimestamp());
        self::assertSame('00:00:00', $clock->today()->format('H:i:s'));
    }

    public function test_the_header_name_is_case_insensitive(): void
    {
        $clock = new RequestClock('test', ['x-fecha-fija' => '2026-04-15']);

        self::assertSame('2026-04-15', $clock->today()->format('Y-m-d'));
    }

    private function realToday(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('America/Bogota')))->format('Y-m-d');
    }
}
