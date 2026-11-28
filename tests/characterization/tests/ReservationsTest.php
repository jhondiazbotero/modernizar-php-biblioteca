<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;

final class ReservationsTest extends CharacterizationTestCase
{
    public function test_reservation_is_created_when_the_book_has_no_available_copy(): void
    {
        $this->app->setDate('2026-11-27 09:30:00');

        $response = $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => 3, 'libro_id' => 4], true);

        $this->assertMessage('ok', 'Reserva registrada para Lucía Fernández Ortiz.', $response);
        self::assertSame('reservas.php?libro_id=4&msg=Reserva+registrada+para+Luc%C3%ADa+Fern%C3%A1ndez+Ortiz.&tipo=ok', AppClient::landedOn($response));
        $row = $this->db->fetchOne('SELECT * FROM reservas WHERE id = 9');
        self::assertSame(4, (int) $row['libro_id']);
        self::assertSame(3, (int) $row['socio_id']);
        self::assertSame('pendiente', $row['estado']);
        self::assertSame('2026-11-27 09:30:00', $row['fecha_reserva']);
        self::assertNull($row['ejemplar_id']);
        self::assertSame(
            [['1', 'Sofía Martínez Vega', '10/11/2026 09:15', 'Pendiente'], ['2', 'Andrés Ramírez Soto', '12/11/2026 16:40', 'Pendiente'], ['3', 'Lucía Fernández Ortiz', '27/11/2026 09:30', 'Pendiente']],
            $this->page($response)->tableRows(0),
        );
    }

    public function test_reservation_made_with_a_date_only_clock_is_stamped_at_noon(): void
    {
        $this->app->setDate('2026-11-27');

        $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => 3, 'libro_id' => 4]);

        self::assertSame('2026-11-27 12:00:00', $this->db->fetchValue('SELECT fecha_reserva FROM reservas WHERE id = 9'));
    }

    public function test_reservation_is_rejected_when_the_book_has_an_available_copy(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => 3, 'libro_id' => 1], true);

        $this->assertMessage('error', 'El libro tiene ejemplares disponibles: no hace falta reservarlo.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_reservation_is_rejected_when_the_member_already_waits_for_the_book(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => 5, 'libro_id' => 4], true);

        $this->assertMessage('error', 'El socio ya tiene una reserva pendiente de ese libro.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_reservation_requires_a_member_and_a_book(): void
    {
        $before = $this->dbState();

        $noMember = $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => '', 'libro_id' => 4], true);
        $this->assertMessage('error', 'Elige un socio.', $noMember);
        $noBook = $this->app->post('reservas.php', ['accion' => 'crear', 'socio_id' => 3, 'libro_id' => ''], true);
        $this->assertMessage('error', 'Elige un libro.', $noBook);

        self::assertSame($before, $this->dbState());
    }

    public function test_cancel_a_pending_reservation(): void
    {
        $response = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 1], true);

        $this->assertMessage('ok', 'Reserva cancelada.', $response);
        self::assertSame('cancelada', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 1'));
        self::assertSame('prestado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 8'));
        self::assertSame('pendiente', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 2'));
    }

    public function test_cancel_an_assigned_reservation_frees_the_copy_when_nobody_waits(): void
    {
        $response = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 3], true);

        $this->assertMessage('ok', 'Reserva cancelada. El ejemplar quedó disponible.', $response);
        self::assertSame('cancelada', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 3'));
        self::assertSame('disponible', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 11'));
    }

    public function test_cancel_an_assigned_reservation_passes_the_copy_to_the_next_in_line(): void
    {
        // Leave a single member waiting for "Emma" so that the choice is unambiguous.
        $this->db->execute("UPDATE reservas SET estado = 'cancelada' WHERE id = 6");

        $response = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 4], true);

        $this->assertMessage('ok', 'Reserva cancelada. Ejemplar apartado para Paula Jiménez Arango', $response);
        self::assertSame('cancelada', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 4'));
        $next = $this->db->fetchOne('SELECT estado, ejemplar_id FROM reservas WHERE id = 5');
        self::assertSame('asignada', $next['estado']);
        self::assertSame(16, (int) $next['ejemplar_id']);
        self::assertSame('reservado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 16'));
    }

    public function test_cancel_gives_the_copy_to_the_member_who_reserved_first(): void
    {
        // Paula reserved on 2026-11-14 and Mateo on 2026-11-16.
        $response = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 4], true);

        $this->assertMessage('ok', 'Reserva cancelada. Ejemplar apartado para Paula Jiménez Arango', $response);
        self::assertSame('asignada', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 5'));
        self::assertSame('pendiente', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 6'));
    }

    public function test_cancel_rejects_unknown_and_closed_reservations(): void
    {
        $before = $this->dbState();

        $unknown = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 999], true);
        $this->assertMessage('error', 'La reserva no existe.', $unknown);
        $cancelled = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 8], true);
        $this->assertMessage('error', 'Esa reserva ya no se puede cancelar.', $cancelled);
        $fulfilled = $this->app->post('reservas.php', ['accion' => 'cancelar', 'reserva_id' => 7], true);
        $this->assertMessage('error', 'Esa reserva ya no se puede cancelar.', $fulfilled);

        self::assertSame($before, $this->dbState());
    }

    public function test_queue_screen_orders_by_reservation_time_and_numbers_the_places(): void
    {
        $html = $this->page($this->app->get('reservas.php', ['libro_id' => 9]));

        self::assertSame('Cola de «Emma»', $html->text('//h3[1]'));
        self::assertSame(
            [
                ['1', 'Sebastián Vargas León', '03/11/2026 10:20', 'Asignada'],
                ['2', 'Paula Jiménez Arango', '14/11/2026 15:05', 'Pendiente'],
                ['3', 'Mateo Salazar Gil', '16/11/2026 10:30', 'Pendiente'],
            ],
            $html->tableRows(0),
        );
    }

    public function test_reservation_form_offers_active_members_only(): void
    {
        $options = $this->page($this->app->get('reservas.php'))->selectOptions('socio_id');

        self::assertCount(12, $options);
        self::assertNotContains('Julián Herrera Cano', $options);
    }
}
