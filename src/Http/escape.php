<?php

declare(strict_types=1);

if (!function_exists('e')) {
    /**
     * Escapes a value for HTML text and attributes. Every value a template prints goes through it.
     *
     * @param mixed $value
     */
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
