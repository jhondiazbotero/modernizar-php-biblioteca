<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\ErrorPage;
use PHPUnit\Framework\TestCase;

final class ErrorPageTest extends TestCase
{
    public function test_not_found_page_is_in_spanish(): void
    {
        $html = ErrorPage::notFound();

        self::assertStringContainsString('<html lang="es">', $html);
        self::assertStringContainsString('Página no encontrada', $html);
    }

    public function test_server_error_page_gives_no_details(): void
    {
        $html = ErrorPage::serverError();

        self::assertStringContainsString('Error interno', $html);
        self::assertStringNotContainsString('DB_', $html);
        self::assertStringNotContainsString('Exception', $html);
    }
}
