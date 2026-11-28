<?php

declare(strict_types=1);

namespace Library\Bootstrap;

use Library\Application\Authenticate;
use Library\Auth\PasswordVerifier;
use Library\Config\DatabaseConfig;
use Library\Config\MissingConfigurationException;
use Library\Database\ConnectionFactory;
use Library\Domain\Clock;
use Library\Http\Controller\AuthController;
use Library\Http\Controller\DashboardController;
use Library\Http\ErrorPage;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Router;
use Library\Http\Session;
use Library\Http\View;
use Library\Infrastructure\Clock\RequestClock;
use Library\Infrastructure\Persistence\PdoLibrarianRepository;
use Library\Infrastructure\Persistence\Query\PdoDashboardQuery;

/**
 * Composition root: builds, by hand, everything a request needs (connection, clock,
 * repositories, use cases and controllers) and answers the request.
 */
final class Application
{
    /** @var string */
    private $rootDir;

    /** @var string|null */
    private $environment;

    /** @var callable */
    private $connect;

    /** @var \PDO|null */
    private $pdo;

    /** @param callable $connect returns the PDO connection; called once, only when a screen needs the database */
    public function __construct(string $rootDir, ?string $environment, callable $connect)
    {
        $this->rootDir = rtrim($rootDir, '/');
        $this->environment = $environment;
        $this->connect = $connect;
    }

    /** Answers a request with the real configuration (environment variables). */
    public static function handle(Request $request): Response
    {
        $environment = getenv('APP_ENV');
        $application = new self(
            dirname(__DIR__, 2),
            $environment === false ? null : $environment,
            static function (): \PDO {
                return ConnectionFactory::create(DatabaseConfig::fromEnvironment());
            }
        );

        return $application->run($request);
    }

    public function run(Request $request): Response
    {
        error_reporting(E_ALL);
        date_default_timezone_set(RequestClock::TIMEZONE);

        try {
            return (new Router($this->factories($request)))->dispatch($request, Session::native());
        } catch (MissingConfigurationException | \PDOException $e) {
            // The message can carry the variable name, host, user or SQL: it goes to the log, never to the page.
            error_log($e->getMessage());

            return Response::html(ErrorPage::serverError(), 500);
        }
    }

    /** @return array<string, callable> */
    private function factories(Request $request): array
    {
        return [
            AuthController::class => function (): AuthController {
                return new AuthController($this->pages(), new Authenticate(new PdoLibrarianRepository($this->pdo()), new PasswordVerifier()));
            },
            DashboardController::class => function () use ($request): DashboardController {
                return new DashboardController($this->pages(), new PdoDashboardQuery($this->pdo()), $this->clock($request));
            },
        ];
    }

    private function pages(): PageRenderer
    {
        return new PageRenderer(new View($this->rootDir . '/templates'));
    }

    private function clock(Request $request): Clock
    {
        return new RequestClock($this->environment, $request->getHeaders());
    }

    private function pdo(): \PDO
    {
        if ($this->pdo === null) {
            $this->pdo = ($this->connect)();
        }

        return $this->pdo;
    }
}
