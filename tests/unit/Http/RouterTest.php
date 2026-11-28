<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Controller\AuthController;
use Library\Http\Controller\DashboardController;
use Library\Http\Controller\MemberController;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Router;
use Library\Http\Session;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    /** Every screen: path => [controller, action, needs a signed-in librarian]. */
    private const EXPECTED = [
        '/' => [DashboardController::class, 'show', true],
        '/index.php' => [DashboardController::class, 'show', true],
        '/socios.php' => [MemberController::class, 'index', true],
        '/socio_editar.php' => [MemberController::class, 'edit', true],
        '/socio_ver.php' => [MemberController::class, 'show', true],
        '/login.php' => [AuthController::class, 'login', false],
        '/logout.php' => [AuthController::class, 'logout', false],
    ];

    /** @var array<int, string> controllers built */
    private $built = [];

    /** @var array<int, string> "Class::action" calls received */
    private $calls = [];

    /** @return array<string, callable> */
    private function factories(): array
    {
        $this->built = [];
        $this->calls = [];
        $factories = [];
        foreach (Router::routes() as $route) {
            $class = $route[0];
            $factories[$class] = function () use ($class) {
                $this->built[] = $class;

                return new class($class, $this->calls) {
                    /** @var string */
                    private $class;

                    /** @var array<int, string> */
                    private $calls;

                    /** @param array<int, string> $calls */
                    public function __construct(string $class, array &$calls)
                    {
                        $this->class = $class;
                        $this->calls = &$calls;
                    }

                    /** @param array<int, mixed> $arguments */
                    public function __call(string $action, array $arguments): Response
                    {
                        $this->calls[] = $this->class . '::' . $action;

                        return Response::html('controlled by ' . $this->class . '::' . $action);
                    }
                };
            };
        }

        return $factories;
    }

    private function router(): Router
    {
        return new Router($this->factories());
    }

    private function signedIn(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1]);
    }

    public function test_the_route_table_is_exactly_the_expected_one(): void
    {
        self::assertSame(self::EXPECTED, Router::routes());
    }

    /** @return array<string, array{string, string, string, bool}> */
    public function routes(): array
    {
        $cases = [];
        foreach (self::EXPECTED as $path => $route) {
            $cases[$path] = [$path, $route[0], $route[1], $route[2]];
        }

        return $cases;
    }

    /** @dataProvider routes */
    public function test_each_route_calls_its_controller_action(string $path, string $class, string $action): void
    {
        $response = $this->router()->dispatch(new Request('GET', $path), $this->signedIn());

        self::assertSame(200, $response->getStatus());
        self::assertSame([$class . '::' . $action], $this->calls);
        self::assertSame([$class], $this->built);
    }

    /** @dataProvider routes */
    public function test_a_protected_route_without_session_goes_to_the_login(string $path, string $class, string $action, bool $protected): void
    {
        $response = $this->router()->dispatch(new Request('GET', $path), Session::inMemory());

        if ($protected) {
            self::assertSame(302, $response->getStatus());
            self::assertSame('login.php', $response->getHeader('Location'));
            self::assertSame([], $this->calls, 'The controller must not run');
            self::assertSame([], $this->built, 'The controller must not even be built');
        } else {
            self::assertSame(200, $response->getStatus());
            self::assertSame([$class . '::' . $action], $this->calls);
        }
    }

    public function test_a_session_without_librarian_is_not_signed_in(): void
    {
        $response = $this->router()->dispatch(new Request('GET', '/'), Session::inMemory([Session::LIBRARIAN_ID => 0]));

        self::assertSame(302, $response->getStatus());
        self::assertSame('login.php', $response->getHeader('Location'));
    }

    public function test_the_http_method_does_not_change_the_route(): void
    {
        $response = $this->router()->dispatch(new Request('POST', '/'), $this->signedIn());

        self::assertSame(200, $response->getStatus());
        self::assertSame([DashboardController::class . '::show'], $this->calls);
    }

    /** @return array<string, array{?string}> */
    public function unknownPaths(): array
    {
        return [
            'unknown screen' => ['/no-existe.php'],
            'legacy directory' => ['/legacy/index.php'],
            'include file' => ['/includes/conexion.php'],
            'parent directory' => ['/../legacy/login.php'],
            'parent directory inside the path' => ['/css/../index.php'],
            'encoded parent directory' => ['/%2e%2e/legacy/login.php'],
            'extra path after the screen' => ['/index.php/extra'],
            'trailing slash on a screen' => ['/index.php/'],
            'double slash' => ['//index.php'],
            'no leading slash' => ['index.php'],
            'different case' => ['/INDEX.PHP'],
            'null byte' => ["/index.php\0.txt"],
            'backslash' => ['/..\\legacy\\login.php'],
            'stylesheet' => ['/css/estilo.css'],
            'empty path' => [''],
            'missing path' => [null],
        ];
    }

    /** @dataProvider unknownPaths */
    public function test_unknown_paths_are_a_404_and_build_nothing(?string $path): void
    {
        $response = $this->router()->dispatch(new Request('GET', $path), $this->signedIn());

        self::assertSame(404, $response->getStatus());
        self::assertStringContainsString('Página no encontrada', $response->getBody());
        self::assertSame([], $this->built);
        self::assertSame([], $this->calls);
    }

    public function test_an_unknown_path_is_a_404_even_without_session(): void
    {
        $response = $this->router()->dispatch(new Request('GET', '/no-existe.php'), Session::inMemory());

        self::assertSame(404, $response->getStatus());
    }

    public function test_handles_tells_which_paths_have_a_controller(): void
    {
        self::assertTrue(Router::handles('/'));
        self::assertTrue(Router::handles('/index.php'));
        self::assertFalse(Router::handles('/no-existe.php'));
        self::assertFalse(Router::handles(null));
    }

    public function test_a_route_without_a_registered_controller_is_a_configuration_error(): void
    {
        $this->expectException(\LogicException::class);

        (new Router([]))->dispatch(new Request('GET', '/'), $this->signedIn());
    }
}
