<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function test_html_is_a_200_with_the_body(): void
    {
        $response = Response::html('<p>Hola</p>');

        self::assertSame(200, $response->getStatus());
        self::assertSame('<p>Hola</p>', $response->getBody());
        self::assertNull($response->getHeader('Location'));
    }

    public function test_html_can_carry_another_status(): void
    {
        self::assertSame(404, Response::html('no', 404)->getStatus());
    }

    public function test_a_redirect_is_a_302_with_a_location_and_no_body(): void
    {
        $response = Response::redirect('login.php');

        self::assertSame(302, $response->getStatus());
        self::assertSame('login.php', $response->getHeader('location'));
        self::assertSame('', $response->getBody());
    }

    public function test_a_redirect_with_a_message_puts_it_in_the_url(): void
    {
        $response = Response::redirectWithMessage('socios.php', 'Socio registrado.');

        self::assertSame('socios.php?msg=Socio+registrado.&tipo=ok', $response->getHeader('Location'));
    }

    public function test_a_redirect_with_a_message_keeps_the_query_it_already_has(): void
    {
        $response = Response::redirectWithMessage('socio_editar.php?id=2', 'Ya existe un socio con ese documento.', 'error');

        self::assertSame('socio_editar.php?id=2&msg=Ya+existe+un+socio+con+ese+documento.&tipo=error', $response->getHeader('Location'));
    }

    public function test_the_message_is_url_encoded(): void
    {
        $response = Response::redirectWithMessage('login.php', 'Sesión cerrada. ¡Hasta pronto!');

        self::assertSame('login.php?msg=Sesi%C3%B3n+cerrada.+%C2%A1Hasta+pronto%21&tipo=ok', $response->getHeader('Location'));
    }
}
