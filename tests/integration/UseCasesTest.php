<?php

declare(strict_types=1);

namespace Library\Tests\Integration;

use Library\Tests\Integration\Support\DatabaseTestCase;

/** The other use cases with the real repositories: the SQL does what the in-memory ones pretend. */
final class UseCasesTest extends DatabaseTestCase
{
    public function test_lending_inserts_the_loan_and_marks_the_copy(): void
    {
        $result = $this->lendCopy()->handle(2, 11);

        self::assertTrue($result->isSuccess());
        self::assertSame('Rayuela', $result->getBookTitle());
        self::assertSame('Zoe Primera', $result->getMemberName());
        self::assertSame('2026-12-11', $result->getDueDate()->format('Y-m-d'));
        self::assertSame('prestado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 11'));
        self::assertSame('2026-11-27', $this->scalar('SELECT fecha_prestamo FROM prestamos WHERE id = ?', [$result->getLoanId()]));
        self::assertSame('2026-12-11', $this->scalar('SELECT fecha_vence FROM prestamos WHERE id = ?', [$result->getLoanId()]));
        self::assertEquals(0, $this->scalar('SELECT multa FROM prestamos WHERE id = ?', [$result->getLoanId()]));
        self::assertEquals(0, $this->scalar('SELECT multa_pagada FROM prestamos WHERE id = ?', [$result->getLoanId()]));
    }

    public function test_lending_a_copy_set_aside_for_the_member_fulfils_the_reservation(): void
    {
        $this->pdo->exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = 11");
        $this->pdo->exec("INSERT INTO reservas (id, libro_id, socio_id, fecha_reserva, estado, ejemplar_id) VALUES (7, 5, 2, '2026-11-02 08:00:00', 'asignada', 11)");

        self::assertSame('copy_reserved_for_other', $this->lendCopy()->handle(3, 11)->getErrorCode());
        self::assertSame('asignada', $this->scalar('SELECT estado FROM reservas WHERE id = 7'));

        self::assertTrue($this->lendCopy()->handle(2, 11)->isSuccess());
        self::assertSame('atendida', $this->scalar('SELECT estado FROM reservas WHERE id = 7'));
        self::assertSame('prestado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 11'));
    }

    public function test_lending_rules_read_the_real_counts(): void
    {
        self::assertSame('member_inactive', $this->lendCopy()->handle(4, 11)->getErrorCode());
        self::assertSame('member_not_found', $this->lendCopy()->handle(99, 11)->getErrorCode());
        self::assertSame('copy_not_found', $this->lendCopy()->handle(1, 99)->getErrorCode());
        self::assertSame('copy_on_loan', $this->lendCopy()->handle(1, 10)->getErrorCode());

        $this->pdo->exec("UPDATE ejemplares SET estado = 'baja' WHERE id = 11");
        self::assertSame('copy_withdrawn', $this->lendCopy()->handle(1, 11)->getErrorCode());

        $this->pdo->exec("UPDATE ejemplares SET estado = 'disponible' WHERE id = 11");
        $this->pdo->exec("INSERT INTO prestamos (ejemplar_id, socio_id, fecha_prestamo, fecha_vence, fecha_devolucion, multa, multa_pagada) VALUES (10, 3, '2026-10-01', '2026-10-15', '2026-10-20', 2500, 0)");
        self::assertSame('unpaid_fines', $this->lendCopy()->handle(3, 11)->getErrorCode());
        $this->pdo->exec('UPDATE prestamos SET multa_pagada = 1 WHERE socio_id = 3');
        self::assertTrue($this->lendCopy()->handle(3, 11)->isSuccess());

        $this->pdo->exec("UPDATE ejemplares SET estado = 'disponible' WHERE id = 10");
        $this->pdo->exec("INSERT INTO prestamos (ejemplar_id, socio_id, fecha_prestamo, fecha_vence) VALUES (10, 2, '2026-11-01', '2026-11-15'), (10, 2, '2026-11-01', '2026-11-15'), (10, 2, '2026-11-01', '2026-11-15')");
        self::assertSame('max_active_loans', $this->lendCopy()->handle(2, 10)->getErrorCode());
    }

    public function test_reserving_needs_a_book_without_available_copies_and_no_duplicate(): void
    {
        self::assertSame('copies_available', $this->placeReservation()->handle(2, 5)->getErrorCode());

        $this->pdo->exec("UPDATE ejemplares SET estado = 'prestado' WHERE id = 11");
        $result = $this->placeReservation()->handle(2, 5);

        self::assertTrue($result->isSuccess());
        self::assertSame('Zoe Primera', $result->getMemberName());
        self::assertSame('pendiente', $this->scalar('SELECT estado FROM reservas WHERE id = ?', [$result->getReservationId()]));
        self::assertSame('2026-11-27 12:00:00', $this->scalar('SELECT fecha_reserva FROM reservas WHERE id = ?', [$result->getReservationId()]));
        self::assertSame('already_pending', $this->placeReservation()->handle(2, 5)->getErrorCode());
        self::assertSame('member_not_found', $this->placeReservation()->handle(99, 5)->getErrorCode());
        self::assertSame('book_not_found', $this->placeReservation()->handle(2, 99)->getErrorCode());
    }

    public function test_cancelling_an_assigned_reservation_passes_the_copy_to_the_next_in_the_queue(): void
    {
        $this->pdo->exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = 11");
        $this->pdo->exec("INSERT INTO reservas (id, libro_id, socio_id, fecha_reserva, estado, ejemplar_id) VALUES
            (7, 5, 1, '2026-11-01 08:00:00', 'asignada', 11),
            (8, 5, 2, '2026-11-02 08:00:00', 'pendiente', NULL),
            (9, 5, 3, '2026-11-09 08:00:00', 'pendiente', NULL)");

        $result = $this->cancelReservation()->handle(7);

        self::assertTrue($result->hadCopy());
        self::assertSame('Álvaro Segundo', $result->getAssignedMemberName());
        self::assertSame('cancelada', $this->scalar('SELECT estado FROM reservas WHERE id = 7'));
        self::assertSame('asignada', $this->scalar('SELECT estado FROM reservas WHERE id = 9'));
        self::assertEquals(11, $this->scalar('SELECT ejemplar_id FROM reservas WHERE id = 9'));
        self::assertSame('pendiente', $this->scalar('SELECT estado FROM reservas WHERE id = 8'));
        self::assertSame('reservado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 11'));

        self::assertSame('not_cancellable', $this->cancelReservation()->handle(7)->getErrorCode());
        self::assertSame('reservation_not_found', $this->cancelReservation()->handle(99)->getErrorCode());
    }

    public function test_cancelling_the_last_assigned_reservation_frees_the_copy(): void
    {
        $this->pdo->exec("UPDATE ejemplares SET estado = 'reservado' WHERE id = 11");
        $this->pdo->exec("INSERT INTO reservas (id, libro_id, socio_id, fecha_reserva, estado, ejemplar_id) VALUES (7, 5, 1, '2026-11-01 08:00:00', 'asignada', 11)");

        $result = $this->cancelReservation()->handle(7);

        self::assertTrue($result->hadCopy());
        self::assertNull($result->getAssignedMemberName());
        self::assertSame('disponible', $this->scalar('SELECT estado FROM ejemplares WHERE id = 11'));
    }

    public function test_paying_a_fine_marks_it_paid_once(): void
    {
        $this->pdo->exec("UPDATE prestamos SET fecha_devolucion = '2026-11-27', multa = 6500 WHERE id = 1");

        $result = $this->payFine()->handle(1);

        self::assertTrue($result->isSuccess());
        self::assertSame(6500, $result->getAmount());
        self::assertEquals(1, $this->scalar('SELECT multa_pagada FROM prestamos WHERE id = 1'));
        self::assertSame('already_paid', $this->payFine()->handle(1)->getErrorCode());
        self::assertSame('loan_not_found', $this->payFine()->handle(99)->getErrorCode());
    }

    public function test_paying_a_loan_without_fine_is_refused(): void
    {
        self::assertSame('no_fine', $this->payFine()->handle(1)->getErrorCode());
    }
}
