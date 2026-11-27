<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;
use Library\Characterization\Html;

final class LoansTest extends CharacterizationTestCase
{
    public function test_loan_is_created_for_14_days_and_the_copy_becomes_loaned(): void
    {
        $response = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20], true);

        $this->assertMessage('ok', 'Préstamo registrado: Rimas y leyendas para Mateo Salazar Gil. Vence el 29/04/2026.', $response);
        self::assertStringStartsWith('prestamos.php?msg=', (string) AppClient::landedOn($response));
        $loan = $this->db->fetchOne('SELECT * FROM prestamos WHERE id = 21');
        self::assertSame(12, (int) $loan['socio_id']);
        self::assertSame(20, (int) $loan['ejemplar_id']);
        self::assertSame('2026-04-15', $loan['fecha_prestamo']);
        self::assertSame('2026-04-29', $loan['fecha_vence']);
        self::assertNull($loan['fecha_devolucion']);
        self::assertSame(0, (int) $loan['multa']);
        self::assertSame(0, (int) $loan['multa_pagada']);
        self::assertSame('prestado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 20'));
        self::assertContains(['Mateo Salazar Gil', 'Rimas y leyendas', 'BIB-0020', '15/04/2026', '29/04/2026', 'Devolver'], $this->page($response)->tableRows());
    }

    public function test_due_date_follows_the_clock_across_a_year_change(): void
    {
        $this->app->setDate('2026-12-25');
        $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20]);

        $loan = $this->db->fetchOne('SELECT fecha_prestamo, fecha_vence FROM prestamos WHERE id = 21');
        self::assertSame('2026-12-25', $loan['fecha_prestamo']);
        self::assertSame('2027-01-08', $loan['fecha_vence']);
    }

    public function test_loan_is_rejected_when_the_member_is_inactive(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('prestar.php', ['socio_id' => 6, 'ejemplar_id' => 20], true);

        $this->assertMessage('error', 'El socio está inactivo y no puede recibir préstamos.', $response);
        self::assertSame('prestar.php?msg=El+socio+est%C3%A1+inactivo+y+no+puede+recibir+pr%C3%A9stamos.&tipo=error', AppClient::landedOn($response));
        self::assertSame($before, $this->dbState());
    }

    public function test_fourth_active_loan_is_rejected(): void
    {
        $before = $this->dbState();
        self::assertSame(3, (int) $this->db->fetchValue('SELECT COUNT(*) FROM prestamos WHERE socio_id = 8 AND fecha_devolucion IS NULL'));

        $response = $this->app->post('prestar.php', ['socio_id' => 8, 'ejemplar_id' => 20], true);

        $this->assertMessage('error', 'El socio ya tiene 3 préstamos activos.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_third_active_loan_is_accepted(): void
    {
        $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 20]);
        $response = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 24], true);

        $this->assertMessage('ok', 'Préstamo registrado: La vorágine para Mateo Salazar Gil. Vence el 29/04/2026.', $response);
        self::assertSame(3, (int) $this->db->fetchValue('SELECT COUNT(*) FROM prestamos WHERE socio_id = 12 AND fecha_devolucion IS NULL'));
    }

    public function test_loan_is_rejected_while_the_member_has_an_unpaid_fine(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('prestar.php', ['socio_id' => 7, 'ejemplar_id' => 20], true);

        $this->assertMessage('error', 'El socio tiene multas sin pagar.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_loan_is_rejected_for_a_loaned_copy(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 1], true);

        $this->assertMessage('error', 'El ejemplar ya está prestado.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_loan_is_rejected_for_a_written_off_copy(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 25], true);

        $this->assertMessage('error', 'El ejemplar está dado de baja.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_loan_is_rejected_for_a_copy_reserved_for_another_member(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => 11], true);

        $this->assertMessage('error', 'El ejemplar está reservado para otro socio.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_loan_of_the_copy_reserved_for_the_same_member_fulfils_the_reservation(): void
    {
        $response = $this->app->post('prestar.php', ['socio_id' => 9, 'ejemplar_id' => 11], true);

        $this->assertMessage('ok', 'Préstamo registrado: Cantos de vida y esperanza para Camila Ríos Duque. Vence el 29/04/2026.', $response);
        self::assertSame('prestado', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 11'));
        self::assertSame('atendida', $this->db->fetchValue('SELECT estado FROM reservas WHERE id = 3'));
        self::assertSame(9, (int) $this->db->fetchValue('SELECT socio_id FROM prestamos WHERE id = 21'));
    }

    public function test_loan_requires_a_member_and_a_copy(): void
    {
        $before = $this->dbState();

        $noMember = $this->app->post('prestar.php', ['socio_id' => '', 'ejemplar_id' => 20], true);
        $this->assertMessage('error', 'Elige un socio.', $noMember);

        $noCopy = $this->app->post('prestar.php', ['socio_id' => 12, 'ejemplar_id' => ''], true);
        $this->assertMessage('error', 'Elige un ejemplar.', $noCopy);

        self::assertSame($before, $this->dbState());
    }

    public function test_loan_form_offers_every_member_and_every_copy_that_is_not_written_off(): void
    {
        $html = $this->page($this->app->get('prestar.php'));

        $members = $html->selectOptions('socio_id');
        self::assertCount(13, $members);
        self::assertSame('Julián Herrera Cano (1001001006) - inactivo', $members['6']);
        $copies = $html->selectOptions('ejemplar_id');
        self::assertCount(25, $copies);
        self::assertSame('Rimas y leyendas - BIB-0020 (disponible)', $copies['20']);
        self::assertArrayNotHasKey('25', $copies);
    }

    public function test_loan_lists_split_active_overdue_and_returned(): void
    {
        $active = $this->page($this->app->get('prestamos.php'));
        self::assertCount(10, $active->tableRows());

        $this->app->setDate('2026-12-01 10:00:00');
        $overdue = $this->page($this->app->get('prestamos.php', ['filtro' => 'vencidos']));
        self::assertSame(['BIB-0009', 'BIB-0019', 'BIB-0008', 'BIB-0001'], array_column($overdue->tableRows(), 2));

        $returned = $this->page($this->app->get('prestamos.php', ['filtro' => 'devueltos']));
        self::assertCount(10, $returned->tableRows());
        self::assertSame(['Socio', 'Libro', 'Ejemplar', 'Prestado', 'Vence', 'Devuelto', 'Multa'], $returned->tableHeaders());
    }

    public function test_unknown_filter_falls_back_to_active_loans(): void
    {
        $html = $this->page($this->app->get('prestamos.php', ['filtro' => 'whatever']));

        self::assertCount(10, $html->tableRows());
        self::assertTrue($html->has('//a[@class="activo"][text()="Activos"]'));
    }

    public function test_dashboard_counts_follow_the_clock(): void
    {
        $early = $this->page($this->app->get('index.php'));
        self::assertSame(['10', 'Préstamos activos', '0', 'Préstamos vencidos', '4', 'Reservas pendientes'], $this->dashboardCards($early));

        $this->app->setDate('2026-12-01');
        $late = $this->page($this->app->get('index.php'));
        self::assertSame(['10', 'Préstamos activos', '4', 'Préstamos vencidos', '4', 'Reservas pendientes'], $this->dashboardCards($late));
    }

    /** @return list<string> figure and label of each card, in order */
    private function dashboardCards(Html $html): array
    {
        $out = [];
        foreach ($html->texts('//div[@class="tarjeta" or @class="tarjeta alerta"]') as $text) {
            [$figure, $label] = explode(' ', $text, 2);
            array_push($out, $figure, $label);
        }

        return $out;
    }
}
