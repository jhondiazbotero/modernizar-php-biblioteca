<?php

declare(strict_types=1);

namespace Library\Domain;

/**
 * Compares names the way MySQL does with utf8mb4_unicode_ci for Latin letters:
 * case and accents do not count («Ñ» is an «n» with a tilde, as in that collation)
 * and trailing spaces are ignored.
 */
final class NameCollation
{
    private const ACCENTS = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a',
        'ç' => 'c',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i',
        'ñ' => 'n',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
    ];

    /** Negative, zero or positive, like strcmp. */
    public static function compare(string $a, string $b): int
    {
        return strcmp(self::key($a), self::key($b));
    }

    private static function key(string $name): string
    {
        return strtr(mb_strtolower(rtrim($name, ' '), 'UTF-8'), self::ACCENTS);
    }
}
