<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\LendCopy;
use Library\Application\ReturnLoan;
use Library\Domain\CopySnapshot;
use Library\Domain\FinePolicy;
use Library\Domain\ReservationQueue;
use Library\Domain\ReservationSnapshot;
use Library\Http\Controller\LoanController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeCopyQuery;
use Library\Tests\Fake\FakeLoanQuery;
use Library\Tests\Fake\FakeMemberQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\FixedClock;
use Library\Tests\Fake\InMemoryCopyRepository;
use Library\Tests\Fake\InMemoryLoanRepository;
use Library\Tests\Fake\InMemoryMemberRepository;
use Library\Tests\Fake\InMemoryReservationRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class LoanControllerTest extends TestCase
{
    /** @var InMemoryStore */
    private $store;

    /** @var FakeLoanQuery */
    private $loanQuery;

    /** @var FakeMemberQuery */
    private $memberQuery;

    /** @var FakeCopyQuery */
    private $copyQuery;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela');
        $this->loanQuery = new FakeLoanQuery();
        $this->memberQuery = new FakeMemberQuery();
        $this->copyQuery = new FakeCopyQuery();
    }

    private function controller(): LoanController
    {
        $clock = new FixedClock('2026-11-27 12:00:00');
        $transactions = new FakeTransactionManager($this->store);
        $members = new InMemoryMemberRepository($this->store);
        $copies = new InMemoryCopyRepository($this->store);
        $loans = new InMemoryLoanRepository($this->store);
        $reservations = new InMemoryReservationRepository($this->store);

        return new LoanController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->loanQuery,
            $this->memberQuery,
            $this->copyQuery,
            new LendCopy($transactions, $members, $copies, $loans, $reservations, $clock),
            new ReturnLoan($transactions, $loans, $copies, $reservations, $clock, new FinePolicy(), new ReservationQueue()),
            $clock
        );
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    /** @param array<string, string> $post */
    private function post(string $path, array $post): Request
    {
        return new Request('POST', $path, [], $post);
    }

    private function location(Response $response): string
    {
        return (string) $response->getHeader('Location');
    }

    public function test_a_loan_is_registered_and_the_list_says_when_it_is_due(): void
    {
        $response = $this->controller()->lend($this->post('/prestar.php', ['socio_id' => '1', 'ejemplar_id' => '10']), $this->session());

        self::assertSame(302, $response->getStatus());
        self::assertSame(
            'prestamos.php?msg=' . urlencode('Préstamo registrado: Rayuela para Ana Torres. Vence el 11/12/2026.') . '&tipo=ok',
            $this->location($response)
        );
        self::assertSame(CopySnapshot::ON_LOAN, $this->store->copyStatus(10));
        self::assertCount(1, $this->store->loans);
    }

    public function test_a_rejected_loan_goes_back_to_the_form_with_the_exact_text(): void
    {
        $this->store->addMember(2, 'Luis Pérez', false);

        $response = $this->controller()->lend($this->post('/prestar.php', ['socio_id' => '2', 'ejemplar_id' => '10']), $this->session());

        self::assertSame(
            'prestar.php?msg=' . urlencode('El socio está inactivo y no puede recibir préstamos.') . '&tipo=error',
            $this->location($response)
        );
        self::assertSame([], $this->store->loans);
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
    }

    /** @return array<string, array{string, string, string}> */
    public function lendErrors(): array
    {
        return [
            'no member chosen' => ['', '10', 'Elige un socio.'],
            'no copy chosen' => ['1', '', 'Elige un ejemplar.'],
            'unknown member' => ['99', '10', 'Elige un socio.'],
            'unknown copy' => ['1', '99', 'Elige un ejemplar.'],
        ];
    }

    /** @dataProvider lendErrors */
    public function test_the_choices_of_the_form_are_checked(string $member, string $copy, string $text): void
    {
        $response = $this->controller()->lend($this->post('/prestar.php', ['socio_id' => $member, 'ejemplar_id' => $copy]), $this->session());

        self::assertSame('prestar.php?msg=' . urlencode($text) . '&tipo=error', $this->location($response));
    }

    public function test_every_lending_error_code_has_its_text(): void
    {
        $cases = [
            'max_active_loans' => ['El socio ya tiene 3 préstamos activos.', function (): void {
                foreach ([20, 21, 22] as $copyId) {
                    $this->store->addCopy($copyId, 6, 'Otro', CopySnapshot::ON_LOAN);
                    $this->store->addLoan($copyId, $copyId, 1, '2026-12-01');
                }
            }],
            'unpaid_fines' => ['El socio tiene multas sin pagar.', function (): void {
                $this->store->addCopy(20, 6, 'Otro', CopySnapshot::AVAILABLE);
                $this->store->addLoan(1, 20, 1, '2026-11-01', '2026-11-14', 6500, false);
            }],
            'copy_on_loan' => ['El ejemplar ya está prestado.', function (): void {
                $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
            }],
            'copy_withdrawn' => ['El ejemplar está dado de baja.', function (): void {
                $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::WITHDRAWN);
            }],
            'copy_reserved_for_other' => ['El ejemplar está reservado para otro socio.', function (): void {
                $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::RESERVED);
            }],
        ];

        foreach ($cases as $code => [$text, $arrange]) {
            $this->setUp();
            $arrange();

            $response = $this->controller()->lend($this->post('/prestar.php', ['socio_id' => '1', 'ejemplar_id' => '10']), $this->session());

            self::assertSame('prestar.php?msg=' . urlencode($text) . '&tipo=error', $this->location($response), $code);
        }
    }

    public function test_the_lending_form_lists_members_and_copies_escaped(): void
    {
        $this->memberQuery->addMember(1, 'Ana <b>Torres</b>');
        $this->memberQuery->addMember(2, 'Luis Pérez', ['active' => false, 'document' => '555']);
        $this->copyQuery->lendable = [
            ['id' => 10, 'code' => 'BIB-1', 'status' => 'disponible', 'title' => 'Rayuela & Co'],
        ];

        $body = $this->controller()->lend(new Request('GET', '/prestar.php'), $this->session())->getBody();

        self::assertStringContainsString('<option value="1">Ana &lt;b&gt;Torres&lt;/b&gt; (1001)</option>', $body);
        self::assertStringContainsString('<option value="2">Luis Pérez (555) - inactivo</option>', $body);
        self::assertStringContainsString('<option value="10">Rayuela &amp; Co - BIB-1 (disponible)</option>', $body);
        self::assertStringContainsString('<title>Prestar un libro - Biblioteca comunitaria</title>', $body);
    }

    public function test_a_late_return_reports_the_fine(): void
    {
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
        $this->store->addLoan(1, 10, 1, '2026-11-01');

        $response = $this->controller()->giveBack($this->post('/devolver.php', ['prestamo_id' => '1']), $this->session());

        self::assertSame(
            'prestamos.php?msg=' . urlencode('Devolución registrada: Rayuela de Ana Torres. Multa por retraso: $ 13.000.') . '&tipo=ok',
            $this->location($response)
        );
        self::assertSame(CopySnapshot::AVAILABLE, $this->store->copyStatus(10));
    }

    public function test_an_on_time_return_has_no_fine_in_the_message(): void
    {
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
        $this->store->addLoan(1, 10, 1, '2026-12-01');

        $response = $this->controller()->giveBack($this->post('/devolver.php', ['prestamo_id' => '1']), $this->session());

        self::assertSame(
            'prestamos.php?msg=' . urlencode('Devolución registrada: Rayuela de Ana Torres.') . '&tipo=ok',
            $this->location($response)
        );
    }

    public function test_a_return_names_the_member_the_copy_is_set_aside_for(): void
    {
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
        $this->store->addLoan(1, 10, 1, '2026-12-01');
        $this->store->addMember(2, 'Luis Pérez');
        $this->store->addReservation(7, 5, 2, ReservationSnapshot::PENDING);

        $response = $this->controller()->giveBack($this->post('/devolver.php', ['prestamo_id' => '1']), $this->session());

        self::assertSame(
            'prestamos.php?msg=' . urlencode('Devolución registrada: Rayuela de Ana Torres. Ejemplar apartado para Luis Pérez') . '&tipo=ok',
            $this->location($response)
        );
    }

    public function test_returning_something_that_does_not_exist_or_was_already_returned_is_reported(): void
    {
        $this->store->addCopy(10, 5, 'Rayuela');
        $this->store->addLoan(1, 10, 1, '2026-12-01', '2026-11-20');

        $unknown = $this->controller()->giveBack($this->post('/devolver.php', ['prestamo_id' => '99']), $this->session());
        $again = $this->controller()->giveBack($this->post('/devolver.php', ['prestamo_id' => '1']), $this->session());
        $none = $this->controller()->giveBack($this->post('/devolver.php', []), $this->session());

        self::assertSame('devolver.php?msg=' . urlencode('Elige el préstamo que se devuelve.') . '&tipo=error', $this->location($unknown));
        self::assertSame('devolver.php?msg=' . urlencode('Ese préstamo ya fue devuelto.') . '&tipo=error', $this->location($again));
        self::assertSame('devolver.php?msg=' . urlencode('Elige el préstamo que se devuelve.') . '&tipo=error', $this->location($none));
    }

    public function test_the_return_form_lists_the_open_loans(): void
    {
        $this->loanQuery->openForReturn = [
            ['id' => 3, 'due_date' => '2026-12-04', 'member' => 'Ana <i>T</i>', 'title' => 'Rayuela', 'code' => 'BIB-1'],
        ];

        $body = $this->controller()->giveBack(new Request('GET', '/devolver.php'), $this->session())->getBody();

        self::assertStringContainsString('<option value="3">Rayuela - BIB-1 - Ana &lt;i&gt;T&lt;/i&gt; (vence 04/12/2026)</option>', $body);
    }

    public function test_the_active_list_is_the_default_and_has_return_buttons(): void
    {
        $this->loanQuery->active = [FakeLoanQuery::loan(3, 'Ana Torres', 'Rayuela', ['due_date' => '2026-11-15'])];

        $body = $this->controller()->index(new Request('GET', '/prestamos.php'), $this->session())->getBody();

        self::assertSame(['active'], $this->loanQuery->asked);
        self::assertStringContainsString('<a href="prestamos.php?filtro=activos" class="activo">Activos</a>', $body);
        self::assertStringContainsString('<td class="vencido">15/11/2026</td>', $body);
        self::assertStringContainsString('<input type="hidden" name="prestamo_id" value="3">', $body);
        self::assertStringNotContainsString('<th>Devuelto</th>', $body);
    }

    public function test_the_overdue_list_asks_for_the_dates_before_today(): void
    {
        $this->loanQuery->overdue = [FakeLoanQuery::loan(3, 'Ana Torres', 'Rayuela')];

        $body = $this->controller()->index(new Request('GET', '/prestamos.php', ['filtro' => 'vencidos']), $this->session())->getBody();

        self::assertSame(['overdue'], $this->loanQuery->asked);
        self::assertSame('2026-11-27', $this->loanQuery->overdueAsked);
        self::assertStringContainsString('<a href="prestamos.php?filtro=vencidos" class="activo">Vencidos</a>', $body);
    }

    public function test_the_returned_list_shows_return_date_and_fine(): void
    {
        $this->loanQuery->returned = [
            FakeLoanQuery::loan(3, 'Ana Torres', 'Rayuela', ['returned_on' => '2026-11-20', 'fine' => 3000.0]),
            FakeLoanQuery::loan(4, 'Luis Pérez', 'Emma', ['returned_on' => '2026-11-14']),
        ];

        $body = $this->controller()->index(new Request('GET', '/prestamos.php', ['filtro' => 'devueltos']), $this->session())->getBody();

        self::assertSame(['returned'], $this->loanQuery->asked);
        self::assertStringContainsString('<th>Devuelto</th>', $body);
        self::assertStringContainsString('<td>20/11/2026</td>', $body);
        self::assertStringContainsString('<td class="num">$ 3.000</td>', $body);
        self::assertStringContainsString('<td class="num">-</td>', $body);
        self::assertStringNotContainsString('Devolver</button>', $body);
    }

    public function test_an_unknown_filter_falls_back_to_the_active_list(): void
    {
        foreach (['otro', '', '1 OR 1=1'] as $filter) {
            $this->loanQuery->asked = [];

            $body = $this->controller()->index(new Request('GET', '/prestamos.php', ['filtro' => $filter]), $this->session())->getBody();

            self::assertSame(['active'], $this->loanQuery->asked, $filter);
            self::assertStringContainsString('<a href="prestamos.php?filtro=activos" class="activo">Activos</a>', $body);
        }
    }

    public function test_an_empty_list_says_so(): void
    {
        $body = $this->controller()->index(new Request('GET', '/prestamos.php'), $this->session())->getBody();

        self::assertStringContainsString('No hay préstamos en esta lista.', $body);
    }
}
