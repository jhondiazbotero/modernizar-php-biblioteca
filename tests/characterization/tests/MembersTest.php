<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;

final class MembersTest extends CharacterizationTestCase
{
    /** @return array<string, scalar> */
    private function newMember(array $overrides = []): array
    {
        return $overrides + [
            'id' => '0',
            'documento' => '2002002001',
            'nombre' => 'Elena Prieto Luna',
            'correo' => 'elena@example.com',
            'telefono' => '3009998877',
            'activo' => '1',
        ];
    }

    public function test_create_member_stores_row_and_redirects_to_list(): void
    {
        $response = $this->app->post('socio_editar.php', $this->newMember(), true);

        self::assertSame('socios.php?msg=Socio+registrado.&tipo=ok', AppClient::landedOn($response));
        $this->assertMessage('ok', 'Socio registrado.', $response);
        $row = $this->db->fetchOne('SELECT * FROM socios WHERE documento = ?', ['2002002001']);
        self::assertNotNull($row);
        self::assertSame(13, (int) $row['id']);
        self::assertSame('Elena Prieto Luna', $row['nombre']);
        self::assertSame('elena@example.com', $row['correo']);
        self::assertSame('3009998877', $row['telefono']);
        self::assertSame('2026-04-15', $row['fecha_alta']);
        self::assertSame(1, (int) $row['activo']);
        self::assertContains('Elena Prieto Luna', array_column($this->page($response)->tableRows(), 1));
    }

    public function test_member_registration_date_comes_from_the_clock(): void
    {
        $this->app->setDate('2027-01-31');
        $this->app->post('socio_editar.php', $this->newMember());

        self::assertSame('2027-01-31', $this->db->fetchValue('SELECT fecha_alta FROM socios WHERE documento = ?', ['2002002001']));
    }

    public function test_create_member_without_active_box_stores_inactive(): void
    {
        $data = $this->newMember();
        unset($data['activo']);
        $this->app->post('socio_editar.php', $data);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT activo FROM socios WHERE documento = ?', ['2002002001']));
    }

    public function test_create_member_trims_the_text_fields(): void
    {
        $this->app->post('socio_editar.php', $this->newMember(['nombre' => '  Elena Prieto Luna  ', 'documento' => ' 2002002001 ']));

        self::assertSame('Elena Prieto Luna', $this->db->fetchValue('SELECT nombre FROM socios WHERE documento = ?', ['2002002001']));
    }

    public function test_create_member_requires_document_and_name(): void
    {
        $before = $this->dbState();

        $noDocument = $this->app->post('socio_editar.php', $this->newMember(['documento' => '']), true);
        $this->assertMessage('error', 'El documento y el nombre son obligatorios.', $noDocument);
        self::assertSame('socio_editar.php?msg=El+documento+y+el+nombre+son+obligatorios.&tipo=error', AppClient::landedOn($noDocument));

        $noName = $this->app->post('socio_editar.php', $this->newMember(['nombre' => '   ']), true);
        $this->assertMessage('error', 'El documento y el nombre son obligatorios.', $noName);

        self::assertSame($before, $this->dbState());
    }

    public function test_create_member_rejects_repeated_document(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('socio_editar.php', $this->newMember(['documento' => '1001001001']), true);

        $this->assertMessage('error', 'Ya existe un socio con ese documento.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_edit_member_updates_row(): void
    {
        $response = $this->app->post('socio_editar.php', $this->newMember([
            'id' => '1',
            'documento' => '1001001001',
            'nombre' => 'Marta Gómez Rincón de Prado',
            'telefono' => '3115550000',
        ]), true);

        $this->assertMessage('ok', 'Socio actualizado.', $response);
        $row = $this->db->fetchOne('SELECT * FROM socios WHERE id = 1');
        self::assertSame('Marta Gómez Rincón de Prado', $row['nombre']);
        self::assertSame('3115550000', $row['telefono']);
        self::assertSame('elena@example.com', $row['correo']);
        self::assertSame('2025-02-10', $row['fecha_alta'], 'Editing must not touch the registration date');
        self::assertSame(12, $this->rows('socios'), 'Editing must not add rows');
    }

    public function test_edit_member_without_active_box_deactivates_it(): void
    {
        $data = $this->newMember(['id' => '1', 'documento' => '1001001001']);
        unset($data['activo']);
        $this->app->post('socio_editar.php', $data);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT activo FROM socios WHERE id = 1'));
    }

    public function test_edit_member_can_keep_its_own_document(): void
    {
        $response = $this->app->post('socio_editar.php', $this->newMember(['id' => '2', 'documento' => '1001001002']), true);

        $this->assertMessage('ok', 'Socio actualizado.', $response);
    }

    public function test_edit_member_rejects_document_of_another_member(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('socio_editar.php', $this->newMember(['id' => '2', 'documento' => '1001001001']), true);

        $this->assertMessage('error', 'Ya existe un socio con ese documento.', $response);
        self::assertSame('socio_editar.php?id=2&msg=Ya+existe+un+socio+con+ese+documento.&tipo=error', AppClient::landedOn($response));
        self::assertSame($before, $this->dbState());
    }

    public function test_edit_member_requires_document_and_name(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('socio_editar.php', $this->newMember(['id' => '2', 'nombre' => '']), true);

        $this->assertMessage('error', 'El documento y el nombre son obligatorios.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_unknown_member_redirects_to_list_with_error(): void
    {
        $edit = $this->app->get('socio_editar.php', ['id' => 999], true);
        self::assertSame('socios.php?msg=El+socio+no+existe.&tipo=error', AppClient::landedOn($edit));
        $this->assertMessage('error', 'El socio no existe.', $edit);

        $view = $this->app->get('socio_ver.php', ['id' => 999], true);
        $this->assertMessage('error', 'El socio no existe.', $view);
    }

    public function test_member_detail_lists_loans_and_pending_fine(): void
    {
        $html = $this->page($this->app->get('socio_ver.php', ['id' => 7]));

        self::assertSame('Valentina Torres Ruiz', $html->heading());
        self::assertSame(
            [['Don Quijote de la Mancha', 'BIB-0003', '01/09/2026', '15/09/2026', '21/09/2026', '$ 3.000 (pendiente)']],
            $html->tableRows(1),
        );
        self::assertSame('Multas pendientes de pago: $ 3.000. Ir a multas', $html->text('//h3[text()="Multas"]/following-sibling::p'));
    }

    public function test_member_detail_without_loans_or_fines(): void
    {
        $this->app->post('socio_editar.php', $this->newMember());
        $id = (int) $this->db->fetchValue('SELECT id FROM socios WHERE documento = ?', ['2002002001']);

        $html = $this->page($this->app->get('socio_ver.php', ['id' => $id]));

        self::assertSame('Elena Prieto Luna', $html->heading());
        self::assertSame('Este socio todavía no tiene préstamos.', $html->text('//h3[text()="Préstamos"]/following-sibling::p'));
        self::assertSame('El socio no tiene multas pendientes.', $html->text('//h3[text()="Multas"]/following-sibling::p'));
    }
}
