<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;

final class FinesTest extends CharacterizationTestCase
{
    public function test_paying_a_fine_marks_it_as_paid(): void
    {
        $response = $this->app->post('multas.php', ['prestamo_id' => 15], true);

        $this->assertMessage('ok', 'Multa pagada: $ 3.000.', $response);
        self::assertSame('multas.php?msg=Multa+pagada%3A+%24+3.000.&tipo=ok', AppClient::landedOn($response));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT multa_pagada FROM prestamos WHERE id = 15'));
        self::assertContains(['Valentina Torres Ruiz', 'Don Quijote de la Mancha', '15/09/2026', '21/09/2026', '$ 3.000', 'Pagada'], $this->page($response)->tableRows());
    }

    public function test_paying_the_fine_unlocks_new_loans_for_the_member(): void
    {
        $blocked = $this->app->post('prestar.php', ['socio_id' => 7, 'ejemplar_id' => 20], true);
        $this->assertMessage('error', 'El socio tiene multas sin pagar.', $blocked);

        $this->app->post('multas.php', ['prestamo_id' => 15]);
        $allowed = $this->app->post('prestar.php', ['socio_id' => 7, 'ejemplar_id' => 20], true);

        $this->assertMessage('ok', 'Préstamo registrado: Rimas y leyendas para Valentina Torres Ruiz. Vence el 29/04/2026.', $allowed);
    }

    public function test_fine_list_shows_unpaid_fines_first(): void
    {
        $rows = $this->page($this->app->get('multas.php'))->tableRows();

        self::assertSame(
            [
                ['Valentina Torres Ruiz', 'Don Quijote de la Mancha', '15/09/2026', '21/09/2026', '$ 3.000', 'Pagar multa'],
                ['Mateo Salazar Gil', 'Marianela', '24/08/2026', '30/08/2026', '$ 3.000', 'Pagada'],
                ['Camila Ríos Duque', 'Fortunata y Jacinta', '18/05/2026', '22/05/2026', '$ 2.000', 'Pagada'],
            ],
            $rows,
        );
    }

    public function test_paying_twice_is_rejected(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('multas.php', ['prestamo_id' => 13], true);

        $this->assertMessage('error', 'Esa multa ya estaba pagada.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_paying_a_loan_without_fine_is_rejected(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('multas.php', ['prestamo_id' => 11], true);

        $this->assertMessage('error', 'Ese préstamo no tiene multa.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_paying_an_unknown_loan_is_rejected(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('multas.php', ['prestamo_id' => 999], true);

        $this->assertMessage('error', 'El préstamo no existe.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_fine_screen_without_fines_says_so(): void
    {
        $this->db->execute('UPDATE prestamos SET multa = 0');

        $html = $this->page($this->app->get('multas.php'));

        self::assertSame('No hay multas registradas.', $html->text('//h2/following-sibling::p'));
    }
}
