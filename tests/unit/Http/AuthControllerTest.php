<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\Authenticate;
use Library\Auth\PasswordVerifier;
use Library\Domain\LibrarianAccount;
use Library\Http\Controller\AuthController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\InMemoryLibrarianRepository;
use PHPUnit\Framework\TestCase;

final class AuthControllerTest extends TestCase
{
    /** @var InMemoryLibrarianRepository */
    private $librarians;

    protected function setUp(): void
    {
        $this->librarians = new InMemoryLibrarianRepository();
        $verifier = new PasswordVerifier();
        $this->librarians->add('admin', new LibrarianAccount(4, 'Administrador', $verifier->hash('s3cret')));
    }

    private function controller(): AuthController
    {
        return new AuthController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            new Authenticate($this->librarians, new PasswordVerifier())
        );
    }

    /** @param array<string, string> $post */
    private function post(array $post): Request
    {
        return new Request('POST', '/login.php', [], $post);
    }

    public function test_the_form_is_shown_without_session(): void
    {
        $response = $this->controller()->login(new Request('GET', '/login.php'), Session::inMemory());

        self::assertSame(200, $response->getStatus());
        self::assertStringContainsString('<h2>Ingreso de bibliotecarios</h2>', $response->getBody());
        self::assertStringContainsString('<title>Ingresar - Biblioteca comunitaria</title>', $response->getBody());
        self::assertStringNotContainsString('id="menu"', $response->getBody());
    }

    public function test_a_signed_in_librarian_is_sent_to_the_dashboard(): void
    {
        $response = $this->controller()->login(new Request('GET', '/login.php'), Session::inMemory([Session::LIBRARIAN_ID => 4]));

        self::assertSame(302, $response->getStatus());
        self::assertSame('index.php', $response->getHeader('Location'));
    }

    public function test_a_good_login_opens_the_session_with_a_new_id_and_goes_to_the_dashboard(): void
    {
        $session = Session::inMemory();

        $response = $this->controller()->login($this->post(['usuario' => 'admin', 'clave' => 's3cret']), $session);

        self::assertSame(302, $response->getStatus());
        self::assertSame('index.php', $response->getHeader('Location'));
        self::assertSame(4, $session->get(Session::LIBRARIAN_ID));
        self::assertSame('Administrador', $session->get(Session::LIBRARIAN_NAME));
        self::assertSame(1, $session->regenerationCount());
    }

    public function test_a_wrong_password_goes_back_to_the_login_with_the_error_and_no_session(): void
    {
        $session = Session::inMemory();

        $response = $this->controller()->login($this->post(['usuario' => 'admin', 'clave' => 'other']), $session);

        self::assertSame('login.php?msg=Usuario+o+clave+incorrectos.&tipo=error', $response->getHeader('Location'));
        self::assertFalse($session->has(Session::LIBRARIAN_ID));
        self::assertSame(0, $session->regenerationCount());
    }

    public function test_fields_that_are_not_text_count_as_empty(): void
    {
        $request = new Request('POST', '/login.php', [], ['usuario' => ['admin'], 'clave' => ['s3cret']]);

        $response = $this->controller()->login($request, Session::inMemory());

        self::assertSame('login.php?msg=Usuario+o+clave+incorrectos.&tipo=error', $response->getHeader('Location'));
    }

    public function test_the_failed_login_shows_its_message_on_the_form(): void
    {
        $request = new Request('GET', '/login.php', ['msg' => 'Usuario o clave incorrectos.', 'tipo' => 'error']);

        $response = $this->controller()->login($request, Session::inMemory());

        self::assertStringContainsString('<div class="mensaje error">Usuario o clave incorrectos.</div>', $response->getBody());
    }

    public function test_logout_destroys_the_session_and_says_goodbye(): void
    {
        $session = Session::inMemory([Session::LIBRARIAN_ID => 4]);

        $response = $this->controller()->logout(new Request('GET', '/logout.php'), $session);

        self::assertTrue($session->isDestroyed());
        self::assertSame(302, $response->getStatus());
        self::assertSame('login.php?msg=Sesi%C3%B3n+cerrada.+%C2%A1Hasta+pronto%21&tipo=ok', $response->getHeader('Location'));
    }
}
