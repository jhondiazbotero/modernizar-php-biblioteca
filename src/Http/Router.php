<?php

declare(strict_types=1);

namespace Library\Http;

use Library\Http\Controller\AuthController;
use Library\Http\Controller\BookController;
use Library\Http\Controller\CopyController;
use Library\Http\Controller\DashboardController;
use Library\Http\Controller\FineController;
use Library\Http\Controller\LoanController;
use Library\Http\Controller\MemberController;
use Library\Http\Controller\ReservationController;

/**
 * Sends each URL path to one controller action through a fixed table.
 *
 * The path is only looked up as a key: nothing taken from the request is ever used to
 * build a class, a method or a file name, so "/../" or "/includes/..." match nothing.
 * Controllers are built on demand, so an unknown path or a visitor without a session
 * never touches the database.
 */
final class Router
{
    /** URL path => [controller class, action, needs a signed-in librarian]. */
    private const ROUTES = [
        '/' => [DashboardController::class, 'show', true],
        '/index.php' => [DashboardController::class, 'show', true],
        '/socios.php' => [MemberController::class, 'index', true],
        '/socio_editar.php' => [MemberController::class, 'edit', true],
        '/socio_ver.php' => [MemberController::class, 'show', true],
        '/libros.php' => [BookController::class, 'index', true],
        '/libro_editar.php' => [BookController::class, 'edit', true],
        '/ejemplares.php' => [CopyController::class, 'index', true],
        '/prestar.php' => [LoanController::class, 'lend', true],
        '/devolver.php' => [LoanController::class, 'giveBack', true],
        '/prestamos.php' => [LoanController::class, 'index', true],
        '/reservas.php' => [ReservationController::class, 'index', true],
        '/multas.php' => [FineController::class, 'index', true],
        '/login.php' => [AuthController::class, 'login', false],
        '/logout.php' => [AuthController::class, 'logout', false],
    ];

    /** @var array<string, callable> controller class => function that builds it */
    private $factories;

    /** @var array<string, object> */
    private $controllers = [];

    /** @param array<string, callable> $factories controller class => function that builds it */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    /** @return array<string, array{string, string, bool}> */
    public static function routes(): array
    {
        return self::ROUTES;
    }

    public static function handles(?string $path): bool
    {
        return $path !== null && isset(self::ROUTES[$path]);
    }

    public function dispatch(Request $request, Session $session): Response
    {
        $path = $request->getPath();
        if ($path === null || !isset(self::ROUTES[$path])) {
            return Response::html(ErrorPage::notFound(), 404);
        }
        [$class, $action, $protected] = self::ROUTES[$path];

        $session->start();
        if ($protected && !$session->isAuthenticated()) {
            return Response::redirect('login.php');
        }

        return $this->controller($class)->{$action}($request, $session);
    }

    private function controller(string $class): object
    {
        if (!isset($this->controllers[$class])) {
            if (!isset($this->factories[$class])) {
                throw new \LogicException('No controller registered for ' . $class);
            }
            $this->controllers[$class] = ($this->factories[$class])();
        }

        return $this->controllers[$class];
    }
}
