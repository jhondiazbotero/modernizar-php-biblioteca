<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\ResponseInterface;

final class ReturnsAndFinesTest extends CharacterizationTestCase
{
    private function returnLoan(int $loanId, string $date): ResponseInterface
    {
        $this->app->setDate($date);

        return $this->app->post('devolver.php', ['prestamo_id' => $loanId], true);
    }

    /** @return array<string, mixed> */
    private function loan(int $id): array
    {
        $row = $this->db->fetchOne('SELECT * FROM prestamos WHERE id = ?', [$id]);
        self::assertNotNull($row);

        return $row;
    }

    public function test_return_on_the_due_date_has_no_fine_and_frees_the_copy(): void
    {
        $response = $this->returnLoan(1, '2026-11-30');

        $this->assertMessage('ok', 'Devolución registrada: Don Quijote de la Mancha de Marta Gómez Rincón.', $response);
        self::assertStringStartsWith('prestamos.php?msg=', (string) AppClient::landedOn($response));
        $loan = $this->loan(1);
        self::assertSame('2026-11-30', $loan['fecha_devolucion']);
        self::assertSame(0, (int) $loan['multa']);
        self::assertSame('disponible', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 1'));
    }

    public function test_return_before_the_due_date_in_another_month_has_no_fine(): void
    {
        // Due 2026-12-02, returned 2026-11-28.
        $response = $this->returnLoan(3, '2026-11-28');

        $this->assertMessage('ok', 'Devolución registrada: Novelas ejemplares de Lucía Fernández Ortiz.', $response);
        self::assertSame(0, (int) $this->loan(3)['multa']);
        self::assertSame('disponible', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 4'));
    }

    public function test_return_late_within_the_same_month_charges_500_per_day(): void
    {
        // Due 2026-11-09, returned 2026-11-22: 13 days.
        $response = $this->returnLoan(5, '2026-11-22');

        $this->assertMessage('ok', 'Devolución registrada: Azul… de Diego Castillo Mora. Multa por retraso: $ 6.500.', $response);
        $loan = $this->loan(5);
        self::assertSame(6500, (int) $loan['multa']);
        self::assertSame(0, (int) $loan['multa_pagada']);
    }

    public function test_fine_of_26_days_is_shown_as_13_thousand_pesos(): void
    {
        // Loan taken on 2026-02-19 is due on 2026-03-05; returned on 2026-03-31.
        $this->app->setDate('2026-02-19');
        $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20]);
        self::assertSame('2026-03-05', $this->loan(21)['fecha_vence']);

        $response = $this->returnLoan(21, '2026-03-31');

        $this->assertMessage('ok', 'Devolución registrada: Rimas y leyendas de Mateo Salazar Gil. Multa por retraso: $ 13.000.', $response);
        self::assertSame(13000, (int) $this->loan(21)['multa']);

        $this->app->setDate('2026-03-31');
        $fines = $this->page($this->app->get('multas.php'));
        self::assertContains(['Mateo Salazar Gil', 'Rimas y leyendas', '05/03/2026', '31/03/2026', '$ 13.000', 'Pagar multa'], $fines->tableRows());
        $member = $this->page($this->app->get('socio_ver.php', ['id' => 12]));
        self::assertContains('$ 13.000 (pendiente)', array_column($member->tableRows(1), 5));
    }

    public function test_late_return_in_the_next_month_charges_the_real_days_late(): void
    {
        // Due 2026-11-16, returned 2026-12-12: 26 days late.
        $response = $this->returnLoan(8, '2026-12-12');

        $this->assertMessage('ok', 'Devolución registrada: Rimas y leyendas de Paula Jiménez Arango. Multa por retraso: $ 13.000.', $response);
        self::assertSame(13000, (int) $this->loan(8)['multa']);
    }

    public function test_late_return_two_months_after_the_due_date_charges_every_day(): void
    {
        // Due 2026-11-09, returned 2026-12-20: 41 days late.
        $response = $this->returnLoan(5, '2026-12-20');

        $this->assertMessage('ok', 'Devolución registrada: Azul… de Diego Castillo Mora. Multa por retraso: $ 20.500.', $response);
        self::assertSame(20500, (int) $this->loan(5)['multa']);
    }

    public function test_return_when_the_book_has_a_reservation_queue_keeps_the_copy_reserved(): void
    {
        // Leave a single member waiting for "Misericordia" so that the choice is unambiguous.
        $this->db->execute("UPDATE reservas SET estado = 'cancelada' WHERE id = 2");

        $response = $this->returnLoan(2, '2026-11-20');

        $this->assertMessage('ok', 'Devolución registrada: Misericordia de Carlos Pérez Mejía. Ejemplar apartado para Sofía Martínez Vega', $response);
        self::assertSame('reservado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 8'));
        $reservation = $this->db->fetchOne('SELECT estado, ejemplar_id FROM reservas WHERE id = 1');
        self::assertSame('asignada', $reservation['estado']);
        self::assertSame(8, (int) $reservation['ejemplar_id']);
    }

    public function test_return_without_a_reservation_queue_makes_the_copy_available(): void
    {
        $this->returnLoan(6, '2026-11-25');

        self::assertSame('disponible', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 14'));
    }

    /**
     * Current behavior (known defect F2): the copy goes to the first member of the queue by name, not to the one who reserved first.
     */
    #[Group('known-defect')]
    public function test_f2_return_gives_the_copy_to_the_first_name_in_alphabetical_order(): void
    {
        // Sofía reserved on 2026-11-10 and Andrés on 2026-11-12.
        $response = $this->returnLoan(2, '2026-11-20');

        $this->assertMessage('ok', 'Devolución registrada: Misericordia de Carlos Pérez Mejía. Ejemplar apartado para Andrés Ramírez Soto', $response);
        self::assertSame('asignada', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 2'));
        self::assertSame('pendiente', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 1'));
        self::assertSame('reservado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 8'));
    }

    public function test_return_of_an_already_returned_loan_is_rejected(): void
    {
        $before = $this->dbState();

        $response = $this->returnLoan(11, '2026-11-20');

        $this->assertMessage('error', 'Ese préstamo ya fue devuelto.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_return_requires_a_loan(): void
    {
        $before = $this->dbState();

        $none = $this->returnLoan(0, '2026-11-20');
        $this->assertMessage('error', 'Elige el préstamo que se devuelve.', $none);
        $unknown = $this->returnLoan(999, '2026-11-20');
        $this->assertMessage('error', 'Elige el préstamo que se devuelve.', $unknown);

        self::assertSame($before, $this->dbState());
    }

    public function test_return_form_lists_active_loans_ordered_by_title(): void
    {
        $options = $this->page($this->app->get('devolver.php'))->selectOptions('prestamo_id');

        self::assertCount(11, $options);
        self::assertSame('Don Quijote de la Mancha - BIB-0001 - Marta Gómez Rincón (vence 30/11/2026)', $options['1']);
    }
}
