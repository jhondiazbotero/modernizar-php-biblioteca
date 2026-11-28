<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\CancelReservation;
use Library\Application\PlaceReservation;
use Library\Domain\CopySnapshot;
use Library\Domain\ReservationQueue;
use Library\Domain\ReservationSnapshot;
use Library\Http\Controller\ReservationController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Response;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeBookQuery;
use Library\Tests\Fake\FakeMemberQuery;
use Library\Tests\Fake\FakeReservationQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\FixedClock;
use Library\Tests\Fake\InMemoryBookRepository;
use Library\Tests\Fake\InMemoryCopyRepository;
use Library\Tests\Fake\InMemoryMemberRepository;
use Library\Tests\Fake\InMemoryReservationRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class ReservationControllerTest extends TestCase
{
    /** @var InMemoryStore */
    private $store;

    /** @var FakeReservationQuery */
    private $reservationQuery;

    /** @var FakeMemberQuery */
    private $memberQuery;

    /** @var FakeBookQuery */
    private $bookQuery;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela', CopySnapshot::ON_LOAN);
        $this->reservationQuery = new FakeReservationQuery();
        $this->memberQuery = new FakeMemberQuery();
        $this->bookQuery = new FakeBookQuery();
    }

    private function controller(): ReservationController
    {
        $transactions = new FakeTransactionManager($this->store);
        $copies = new InMemoryCopyRepository($this->store);
        $reservations = new InMemoryReservationRepository($this->store);

        return new ReservationController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->reservationQuery,
            $this->memberQuery,
            $this->bookQuery,
            new PlaceReservation(
                $transactions,
                new InMemoryMemberRepository($this->store),
                new InMemoryBookRepository($this->store),
                $copies,
                $reservations,
                new FixedClock('2026-11-27 12:00:00')
            ),
            new CancelReservation($transactions, $reservations, $copies, new ReservationQueue())
        );
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    /** @param array<string, string> $post */
    private function post(array $post): Request
    {
        return new Request('POST', '/reservas.php', [], $post);
    }

    private function location(Response $response): string
    {
        return (string) $response->getHeader('Location');
    }

    public function test_a_reservation_is_made_and_the_queue_of_the_book_is_shown(): void
    {
        $response = $this->controller()->index($this->post(['accion' => 'crear', 'socio_id' => '1', 'libro_id' => '5']), $this->session());

        self::assertSame('reservas.php?libro_id=5&msg=' . urlencode('Reserva registrada para Ana Torres.') . '&tipo=ok', $this->location($response));
        self::assertSame(ReservationSnapshot::PENDING, $this->store->reservations[1]['status']);
    }

    public function test_every_reservation_error_has_its_text(): void
    {
        $cases = [
            'no member' => [['socio_id' => '', 'libro_id' => '5'], 'Elige un socio.'],
            'unknown book' => [['socio_id' => '1', 'libro_id' => '99'], 'Elige un libro.'],
            'copy available' => [['socio_id' => '1', 'libro_id' => '6'], 'El libro tiene ejemplares disponibles: no hace falta reservarlo.'],
            'repeated' => [['socio_id' => '1', 'libro_id' => '5'], 'El socio ya tiene una reserva pendiente de ese libro.'],
        ];
        $this->store->addCopy(11, 6, 'Emma');
        $this->store->addReservation(1, 5, 1, ReservationSnapshot::PENDING);

        foreach ($cases as $name => [$post, $text]) {
            $response = $this->controller()->index($this->post(['accion' => 'crear'] + $post), $this->session());

            self::assertSame('reservas.php?msg=' . urlencode($text) . '&tipo=error', $this->location($response), $name);
        }
    }

    public function test_cancelling_a_pending_reservation(): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::PENDING);

        $response = $this->controller()->index($this->post(['accion' => 'cancelar', 'reserva_id' => '7']), $this->session());

        self::assertSame('reservas.php?msg=' . urlencode('Reserva cancelada.') . '&tipo=ok', $this->location($response));
        self::assertSame(ReservationSnapshot::CANCELLED, $this->store->reservations[7]['status']);
    }

    public function test_cancelling_an_assigned_reservation_frees_the_copy(): void
    {
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::RESERVED);
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::ASSIGNED, 11);

        $response = $this->controller()->index($this->post(['accion' => 'cancelar', 'reserva_id' => '7']), $this->session());

        self::assertSame('reservas.php?msg=' . urlencode('Reserva cancelada. El ejemplar quedó disponible.') . '&tipo=ok', $this->location($response));
    }

    public function test_cancelling_an_assigned_reservation_sets_the_copy_aside_for_the_next_in_line(): void
    {
        $this->store->addMember(2, 'Luis Pérez');
        $this->store->addCopy(11, 5, 'Rayuela', CopySnapshot::RESERVED);
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::ASSIGNED, 11);
        $this->store->addReservation(8, 5, 2, ReservationSnapshot::PENDING);

        $response = $this->controller()->index($this->post(['accion' => 'cancelar', 'reserva_id' => '7']), $this->session());

        self::assertSame('reservas.php?msg=' . urlencode('Reserva cancelada. Ejemplar apartado para Luis Pérez') . '&tipo=ok', $this->location($response));
    }

    public function test_cancelling_something_that_cannot_be_cancelled_is_reported(): void
    {
        $this->store->addReservation(7, 5, 1, ReservationSnapshot::FULFILLED);

        $unknown = $this->controller()->index($this->post(['accion' => 'cancelar', 'reserva_id' => '99']), $this->session());
        $done = $this->controller()->index($this->post(['accion' => 'cancelar', 'reserva_id' => '7']), $this->session());

        self::assertSame('reservas.php?msg=' . urlencode('La reserva no existe.') . '&tipo=error', $this->location($unknown));
        self::assertSame('reservas.php?msg=' . urlencode('Esa reserva ya no se puede cancelar.') . '&tipo=error', $this->location($done));
    }

    public function test_the_page_lists_the_current_reservations_and_the_form_options(): void
    {
        $this->reservationQuery->current = [
            ['id' => 7, 'book_id' => 5, 'title' => 'Rayuela', 'member' => 'Ana <b>Torres</b>', 'reserved_at' => '2026-11-05 15:30:00', 'status' => 'pendiente', 'code' => null],
            ['id' => 8, 'book_id' => 6, 'title' => 'Emma', 'member' => 'Luis Pérez', 'reserved_at' => '2026-11-06 09:00:00', 'status' => 'asignada', 'code' => 'BIB-9'],
        ];
        $this->memberQuery->addMember(1, 'Ana Torres');
        $this->bookQuery->addBook(5, 'Rayuela', 'Julio Cortázar');

        $body = $this->controller()->index(new Request('GET', '/reservas.php'), $this->session())->getBody();

        self::assertStringContainsString('<td>Ana &lt;b&gt;Torres&lt;/b&gt;</td>', $body);
        self::assertStringContainsString('<td>05/11/2026 15:30</td>', $body);
        self::assertStringContainsString('<td>Pendiente</td>', $body);
        self::assertStringContainsString('<td>Asignada</td>', $body);
        self::assertStringContainsString('<td>-</td>', $body);
        self::assertStringContainsString('<td>BIB-9</td>', $body);
        self::assertStringContainsString('<input type="hidden" name="reserva_id" value="8">', $body);
        self::assertStringContainsString('<option value="1">Ana Torres</option>', $body);
        self::assertStringContainsString('<option value="5">Rayuela - Julio Cortázar</option>', $body);
        self::assertStringNotContainsString('<h3>Cola de', $body);
        self::assertSame([], $this->reservationQuery->queuesAsked);
    }

    public function test_the_queue_of_a_book_is_shown_with_places(): void
    {
        $this->bookQuery->addBook(5, 'Rayuela <1>', 'Julio Cortázar');
        $this->reservationQuery->queues[5] = [
            ['member' => 'Ana Torres', 'reserved_at' => '2026-11-05 15:30:00', 'status' => 'asignada'],
            ['member' => 'Luis Pérez', 'reserved_at' => '2026-11-06 09:00:00', 'status' => 'pendiente'],
        ];

        $body = $this->controller()->index(new Request('GET', '/reservas.php', ['libro_id' => '5']), $this->session())->getBody();

        self::assertStringContainsString('<h3>Cola de «Rayuela &lt;1&gt;»</h3>', $body);
        self::assertStringContainsString('<td class="num">1</td>', $body);
        self::assertStringContainsString('<td class="num">2</td>', $body);
    }

    public function test_the_queue_of_a_book_without_reservations_says_so(): void
    {
        $this->bookQuery->addBook(5, 'Rayuela', 'Julio Cortázar');

        $body = $this->controller()->index(new Request('GET', '/reservas.php', ['libro_id' => '5']), $this->session())->getBody();

        self::assertStringContainsString('Este libro no tiene reservas en cola.', $body);
    }

    public function test_the_queue_of_an_unknown_book_is_not_shown(): void
    {
        $body = $this->controller()->index(new Request('GET', '/reservas.php', ['libro_id' => '99']), $this->session())->getBody();

        self::assertStringNotContainsString('<h3>Cola de', $body);
        self::assertStringContainsString('No hay reservas vigentes.', $body);
    }

    public function test_a_post_with_an_unknown_action_just_shows_the_page(): void
    {
        $response = $this->controller()->index($this->post(['accion' => 'otra']), $this->session());

        self::assertSame(200, $response->getStatus());
    }
}
