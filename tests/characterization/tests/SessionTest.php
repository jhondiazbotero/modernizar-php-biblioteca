<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class SessionTest extends CharacterizationTestCase
{
    public function test_good_login_redirects_to_dashboard(): void
    {
        $client = $this->anonymous();

        $response = $client->login();
        $this->assertRedirectsTo('index.php', $response);

        $dashboard = $client->get('index.php');
        self::assertSame(200, $dashboard->getStatusCode());
        self::assertSame('Tablero', $this->page($dashboard)->heading());
        self::assertSame('Salir (Administrador)', $this->page($dashboard)->text('//li[@class="salir"]'));
    }

    public function test_good_login_followed_lands_on_dashboard(): void
    {
        $response = $this->anonymous()->login('admin', null, true);

        self::assertSame('index.php', AppClient::landedOn($response));
        self::assertSame('Tablero', $this->page($response)->heading());
    }

    public function test_wrong_password_shows_error(): void
    {
        $client = $this->anonymous();

        $response = $client->login('admin', 'not-the-password', true);

        self::assertSame('login.php?msg=Usuario+o+clave+incorrectos.&tipo=error', AppClient::landedOn($response));
        $this->assertMessage('error', 'Usuario o clave incorrectos.', $response);
        self::assertSame(302, $client->get('index.php')->getStatusCode(), 'A failed login must not open a session');
    }

    public function test_unknown_user_shows_error(): void
    {
        $response = $this->anonymous()->login('nobody', null, true);

        $this->assertMessage('error', 'Usuario o clave incorrectos.', $response);
    }

    public function test_login_form_redirects_to_dashboard_when_already_logged_in(): void
    {
        $this->assertRedirectsTo('index.php', $this->app->get('login.php'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function protectedScreens(): iterable
    {
        foreach ([
            'index.php', 'socios.php', 'socio_editar.php', 'socio_ver.php?id=1', 'libros.php',
            'libro_editar.php', 'ejemplares.php?libro_id=1', 'prestar.php', 'devolver.php',
            'prestamos.php', 'reservas.php', 'multas.php', 'buscar.php',
        ] as $path) {
            yield $path => [$path];
        }
    }

    #[DataProvider('protectedScreens')]
    public function test_protected_screen_without_session_redirects_to_login(string $path): void
    {
        $this->assertRedirectsTo('login.php', $this->anonymous()->get($path));
    }

    /**
     * @return iterable<string, array{string, array<string, scalar>}>
     */
    public static function protectedActions(): iterable
    {
        yield 'create member' => ['socio_editar.php', ['documento' => '9009009', 'nombre' => 'Intruder']];
        yield 'create book' => ['libro_editar.php', ['titulo' => 'Intruder', 'autor' => 'Intruder']];
        yield 'add copy' => ['ejemplares.php', ['accion' => 'alta', 'libro_id' => 1, 'codigo' => 'BIB-9999']];
        yield 'lend' => ['prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20]];
        yield 'return' => ['devolver.php', ['prestamo_id' => 1]];
        yield 'pay fine' => ['multas.php', ['prestamo_id' => 15]];
        yield 'cancel reservation' => ['reservas.php', ['accion' => 'cancelar', 'reserva_id' => 1]];
        yield 'create reservation' => ['reservas.php', ['accion' => 'crear', 'socio_id' => 3, 'libro_id' => 4]];
    }

    /**
     * @param array<string, scalar> $data
     */
    #[DataProvider('protectedActions')]
    public function test_write_action_without_session_redirects_and_changes_nothing(string $path, array $data): void
    {
        $before = $this->dbState();

        $this->assertRedirectsTo('login.php', $this->anonymous()->post($path, $data));

        self::assertSame($before, $this->dbState());
    }

    public function test_logout_closes_the_session(): void
    {
        $response = $this->app->get('logout.php', [], true);

        self::assertSame('login.php?msg=Sesi%C3%B3n+cerrada.+%C2%A1Hasta+pronto%21&tipo=ok', AppClient::landedOn($response));
        $this->assertMessage('ok', 'Sesión cerrada. ¡Hasta pronto!', $response);
        $this->assertRedirectsTo('login.php', $this->app->get('prestamos.php'));
    }
}
