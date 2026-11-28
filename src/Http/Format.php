<?php

declare(strict_types=1);

namespace Library\Http;

/** How money, dates and states are written on screen. */
final class Format
{
    /** Colombian pesos: $ 1.500 (the database hands DECIMAL columns over as strings). @param mixed $value */
    public static function money($value): string
    {
        return '$ ' . number_format((float) $value, 0, ',', '.');
    }

    /** From 2026-03-28 to 28/03/2026; a dash when there is no date. @param mixed $value */
    public static function date($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return date('d/m/Y', (int) strtotime((string) $value));
    }

    /** From 2026-03-28 18:30:00 to 28/03/2026 18:30. @param mixed $value */
    public static function dateTime($value): string
    {
        return date('d/m/Y H:i', (int) strtotime((string) $value));
    }

    /** @param mixed $active */
    public static function memberStatus($active): string
    {
        return $active ? 'Activo' : 'Inactivo';
    }
}
