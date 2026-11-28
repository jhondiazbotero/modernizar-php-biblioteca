<?php

declare(strict_types=1);

namespace Library\Infrastructure\Clock;

use Library\Domain\Clock;

/**
 * Real time in America/Bogota. Only when the environment is "test" and the request
 * carries a valid X-Fecha-Fija header ('Y-m-d' or 'Y-m-d H:i:s') is the date fixed;
 * a date alone means 12:00:00. Anything else is ignored.
 */
final class RequestClock implements Clock
{
    public const TIMEZONE = 'America/Bogota';
    public const HEADER = 'X-Fecha-Fija';

    /** @var string|null */
    private $environment;

    /** @var array<string, string> */
    private $headers;

    /** @param array<string, mixed> $headers request headers by name (case does not matter) */
    public function __construct(?string $environment, array $headers)
    {
        $this->environment = $environment;
        $this->headers = [];
        foreach ($headers as $name => $value) {
            if (is_string($value)) {
                $this->headers[strtolower((string) $name)] = $value;
            }
        }
    }

    public function today(): \DateTimeImmutable
    {
        $fixed = $this->fixedMoment();
        if ($fixed !== null) {
            return $fixed->setTime(0, 0, 0);
        }

        return new \DateTimeImmutable('today', self::timezone());
    }

    public function now(): \DateTimeImmutable
    {
        $fixed = $this->fixedMoment();

        return $fixed !== null ? $fixed : new \DateTimeImmutable('now', self::timezone());
    }

    private function fixedMoment(): ?\DateTimeImmutable
    {
        if ($this->environment !== 'test') {
            return null;
        }
        $value = $this->headers[strtolower(self::HEADER)] ?? '';
        if ($value === '' || $value === '0') {
            return null;
        }
        foreach (['Y-m-d H:i:s', 'Y-m-d'] as $format) {
            $parsed = \DateTimeImmutable::createFromFormat('!' . $format, $value, self::timezone());
            if ($parsed !== false && $parsed->format($format) === $value) {
                return $format === 'Y-m-d' ? $parsed->setTime(12, 0, 0) : $parsed;
            }
        }

        return null;
    }

    private static function timezone(): \DateTimeZone
    {
        return new \DateTimeZone(self::TIMEZONE);
    }
}
