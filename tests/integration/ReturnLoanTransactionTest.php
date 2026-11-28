<?php

declare(strict_types=1);

namespace Library\Tests\Integration;

use Library\Tests\Integration\Support\DatabaseTestCase;
use Library\Tests\Integration\Support\FailingCopyRepository;
use Library\Tests\Integration\Support\FailingReservationRepository;

final class ReturnLoanTransactionTest extends DatabaseTestCase
{
    public function test_a_return_commits_the_loan_the_fine_and_the_copy_together(): void
    {
        $result = $this->returnLoan()->handle(1);

        self::assertTrue($result->isSuccess());
        self::assertSame(6500, $result->getFine());
        self::assertSame('2026-11-27', $this->scalar('SELECT fecha_devolucion FROM prestamos WHERE id = 1'));
        self::assertEquals(6500, $this->scalar('SELECT multa FROM prestamos WHERE id = 1'));
        self::assertSame('disponible', $this->scalar('SELECT estado FROM ejemplares WHERE id = 10'));
    }

    public function test_the_next_member_in_the_queue_gets_the_copy_in_the_same_transaction(): void
    {
        $this->pdo->exec("INSERT INTO reservas (id, libro_id, socio_id, fecha_reserva, estado) VALUES
            (7, 5, 2, '2026-11-02 08:00:00', 'pendiente'),
            (8, 5, 3, '2026-11-09 08:00:00', 'pendiente')");

        $result = $this->returnLoan()->handle(1);

        self::assertSame('Zoe Primera', $result->getAssignedMemberName());
        self::assertSame('asignada', $this->scalar('SELECT estado FROM reservas WHERE id = 7'));
        self::assertEquals(10, $this->scalar('SELECT ejemplar_id FROM reservas WHERE id = 7'));
        self::assertSame('pendiente', $this->scalar('SELECT estado FROM reservas WHERE id = 8'));
        self::assertSame('reservado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 10'));
    }

    public function test_a_failure_after_the_first_update_leaves_the_loan_and_the_copy_as_they_were(): void
    {
        try {
            $this->returnLoan(new FailingCopyRepository($this->copyRepository()))->handle(1);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            self::assertSame('Forced failure while updating the copy.', $e->getMessage());
        }

        self::assertNull($this->scalar('SELECT fecha_devolucion FROM prestamos WHERE id = 1'));
        self::assertEquals(0, $this->scalar('SELECT multa FROM prestamos WHERE id = 1'));
        self::assertSame('prestado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 10'));
        self::assertFalse($this->pdo->inTransaction());
    }

    public function test_a_failure_while_assigning_the_reservation_leaves_everything_as_it_was(): void
    {
        $this->pdo->exec("INSERT INTO reservas (id, libro_id, socio_id, fecha_reserva, estado) VALUES
            (7, 5, 2, '2026-11-02 08:00:00', 'pendiente')");

        try {
            $this->returnLoan(null, new FailingReservationRepository($this->reservationRepository()))->handle(1);
            self::fail('The forced failure must reach the caller.');
        } catch (\RuntimeException $e) {
            self::assertSame('Forced failure while assigning the reservation.', $e->getMessage());
        }

        self::assertNull($this->scalar('SELECT fecha_devolucion FROM prestamos WHERE id = 1'));
        self::assertEquals(0, $this->scalar('SELECT multa FROM prestamos WHERE id = 1'));
        self::assertSame('prestado', $this->scalar('SELECT estado FROM ejemplares WHERE id = 10'));
        self::assertSame('pendiente', $this->scalar('SELECT estado FROM reservas WHERE id = 7'));
        self::assertNull($this->scalar('SELECT ejemplar_id FROM reservas WHERE id = 7'));
    }

    public function test_the_loan_that_failed_can_be_returned_afterwards(): void
    {
        try {
            $this->returnLoan(new FailingCopyRepository($this->copyRepository()))->handle(1);
        } catch (\RuntimeException $e) {
            // expected: nothing was kept
        }

        self::assertTrue($this->returnLoan()->handle(1)->isSuccess());
        self::assertSame('2026-11-27', $this->scalar('SELECT fecha_devolucion FROM prestamos WHERE id = 1'));
    }

    public function test_errors_leave_the_database_untouched(): void
    {
        self::assertSame('loan_not_found', $this->returnLoan()->handle(99)->getErrorCode());
        $this->pdo->exec("UPDATE prestamos SET fecha_devolucion = '2026-11-10' WHERE id = 1");
        self::assertSame('already_returned', $this->returnLoan()->handle(1)->getErrorCode());
        self::assertSame('2026-11-10', $this->scalar('SELECT fecha_devolucion FROM prestamos WHERE id = 1'));
    }
}
