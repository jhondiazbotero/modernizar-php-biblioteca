<?php

declare(strict_types=1);

namespace Library\Http;

/** Minimal error pages (in Spanish, like the rest of what the user sees). */
final class ErrorPage
{
    public static function notFound(): string
    {
        return self::render('Página no encontrada', 'La página que buscas no existe.');
    }

    public static function serverError(): string
    {
        return self::render('Error interno', 'Ocurrió un problema. Intenta de nuevo más tarde.');
    }

    private static function render(string $title, string $message): string
    {
        return "<!DOCTYPE html>\n"
            . "<html lang=\"es\">\n"
            . "<head><meta charset=\"utf-8\"><title>{$title}</title></head>\n"
            . "<body><h1>{$title}</h1><p>{$message}</p></body>\n"
            . "</html>\n";
    }
}
