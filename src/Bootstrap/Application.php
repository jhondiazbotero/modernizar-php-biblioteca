<?php

declare(strict_types=1);

namespace Library\Bootstrap;

use Library\Application\AddCopy;
use Library\Application\Authenticate;
use Library\Application\EditBook;
use Library\Application\CancelReservation;
use Library\Application\LendCopy;
use Library\Application\PayFine;
use Library\Application\PlaceReservation;
use Library\Application\EditMember;
use Library\Application\RegisterBook;
use Library\Application\RegisterMember;
use Library\Application\ReturnLoan;
use Library\Application\WithdrawCopy;
use Library\Auth\PasswordVerifier;
use Library\Config\DatabaseConfig;
use Library\Config\MissingConfigurationException;
use Library\Database\ConnectionFactory;
use Library\Domain\Clock;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;
use Library\Http\Controller\AuthController;
use Library\Http\Controller\BookController;
use Library\Http\Controller\CopyController;
use Library\Http\Controller\DashboardController;
use Library\Http\Controller\FineController;
use Library\Http\Controller\LoanController;
use Library\Http\Controller\MemberController;
use Library\Http\Controller\ReservationController;
use Library\Http\Controller\SearchController;
use Library\Http\ErrorPage;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Router;
use Library\Http\Session;
use Library\Http\View;
use Library\Infrastructure\Clock\RequestClock;
use Library\Infrastructure\Persistence\PdoBookRepository;
use Library\Infrastructure\Persistence\PdoCopyRepository;
use Library\Infrastructure\Persistence\PdoLibrarianRepository;
use Library\Infrastructure\Persistence\PdoLoanRepository;
use Library\Infrastructure\Persistence\PdoMemberRepository;
use Library\Infrastructure\Persistence\PdoReservationRepository;
use Library\Infrastructure\Persistence\PdoTransactionManager;
use Library\Infrastructure\Persistence\Query\PdoBookQuery;
use Library\Infrastructure\Persistence\Query\PdoCopyQuery;
use Library\Infrastructure\Persistence\Query\PdoDashboardQuery;
use Library\Infrastructure\Persistence\Query\PdoFineQuery;
use Library\Infrastructure\Persistence\Query\PdoLoanQuery;
use Library\Infrastructure\Persistence\Query\PdoMemberQuery;
use Library\Infrastructure\Persistence\Query\PdoReservationQuery;
use Library\Infrastructure\Persistence\Query\PdoSearchQuery;

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
            FineController::class => function (): FineController {
                return new FineController(
                    $this->pages(),
                    new PdoFineQuery($this->pdo()),
                    new PayFine(new PdoTransactionManager($this->pdo()), new PdoLoanRepository($this->pdo()))
                );
            },
            LoanController::class => function () use ($request): LoanController {
                $clock = $this->clock($request);
                $transactions = new PdoTransactionManager($this->pdo());
                $members = new PdoMemberRepository($this->pdo());
                $copies = new PdoCopyRepository($this->pdo());
                $loans = new PdoLoanRepository($this->pdo());
                $reservations = new PdoReservationRepository($this->pdo());

                return new LoanController(
                    $this->pages(),
                    new PdoLoanQuery($this->pdo()),
                    new PdoMemberQuery($this->pdo()),
                    new PdoCopyQuery($this->pdo()),
                    new LendCopy($transactions, $members, $copies, $loans, $reservations, $clock),
                    new ReturnLoan($transactions, $loans, $copies, $reservations, $clock, new FinePolicy(), new ReservationQueue()),
                    $clock
                );
            },
            ReservationController::class => function () use ($request): ReservationController {
                $transactions = new PdoTransactionManager($this->pdo());
                $copies = new PdoCopyRepository($this->pdo());
                $reservations = new PdoReservationRepository($this->pdo());

                return new ReservationController(
                    $this->pages(),
                    new PdoReservationQuery($this->pdo()),
                    new PdoMemberQuery($this->pdo()),
                    new PdoBookQuery($this->pdo()),
                    new PlaceReservation(
                        $transactions,
                        new PdoMemberRepository($this->pdo()),
                        new PdoBookRepository($this->pdo()),
                        $copies,
                        $reservations,
                        $this->clock($request)
                    ),
                    new CancelReservation($transactions, $reservations, $copies, new ReservationQueue())
                );
            },
            SearchController::class => function (): SearchController {
                return new SearchController($this->pages(), new PdoSearchQuery($this->pdo()));
            },
            MemberController::class => function () use ($request): MemberController {
                $clock = $this->clock($request);
                $transactions = new PdoTransactionManager($this->pdo());
                $members = new PdoMemberRepository($this->pdo());

                return new MemberController(
                    $this->pages(),
                    new PdoMemberQuery($this->pdo()),
                    new RegisterMember($transactions, $members, $clock),
                    new EditMember($transactions, $members),
                    $clock
                );
            },
            BookController::class => function (): BookController {
                $transactions = new PdoTransactionManager($this->pdo());
                $books = new PdoBookRepository($this->pdo());

                return new BookController(
                    $this->pages(),
                    new PdoBookQuery($this->pdo()),
                    new RegisterBook($transactions, $books),
                    new EditBook($transactions, $books)
                );
            },
            CopyController::class => function (): CopyController {
                $transactions = new PdoTransactionManager($this->pdo());
                $copies = new PdoCopyRepository($this->pdo());

                return new CopyController(
                    $this->pages(),
                    new PdoBookQuery($this->pdo()),
                    new PdoCopyQuery($this->pdo()),
                    new AddCopy($transactions, new PdoBookRepository($this->pdo()), $copies),
                    new WithdrawCopy($transactions, $copies)
                );
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
