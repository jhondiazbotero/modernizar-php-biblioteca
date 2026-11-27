<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\AppClient;
use Library\Characterization\CharacterizationTestCase;

final class BooksAndCopiesTest extends CharacterizationTestCase
{
    /** @return array<string, scalar> */
    private function newBook(array $overrides = []): array
    {
        return $overrides + [
            'id' => '0',
            'titulo' => 'El túnel',
            'autor' => 'Ernesto Sabato',
            'anio' => '1948',
            'isbn' => '978-0-0000-0099-9',
        ];
    }

    public function test_create_book_stores_row_and_goes_to_its_copies(): void
    {
        $response = $this->app->post('libro_editar.php', $this->newBook(), true);

        self::assertSame(
            'ejemplares.php?libro_id=16&msg=Libro+registrado.+Ahora+puedes+agregar+sus+ejemplares.&tipo=ok',
            AppClient::landedOn($response),
        );
        $this->assertMessage('ok', 'Libro registrado. Ahora puedes agregar sus ejemplares.', $response);
        self::assertSame('Ejemplares: El túnel', $this->page($response)->heading());
        $row = $this->db->fetchOne('SELECT * FROM libros WHERE id = 16');
        self::assertSame('El túnel', $row['titulo']);
        self::assertSame('Ernesto Sabato', $row['autor']);
        self::assertSame(1948, (int) $row['anio']);
        self::assertSame('978-0-0000-0099-9', $row['isbn']);
    }

    public function test_create_book_without_year_stores_null(): void
    {
        $this->app->post('libro_editar.php', $this->newBook(['anio' => '']));

        self::assertNull($this->db->fetchValue('SELECT anio FROM libros WHERE id = 16'));
    }

    public function test_create_book_requires_title_and_author(): void
    {
        $before = $this->dbState();

        $noTitle = $this->app->post('libro_editar.php', $this->newBook(['titulo' => '']), true);
        $this->assertMessage('error', 'El título y el autor son obligatorios.', $noTitle);
        self::assertSame('libro_editar.php?msg=El+t%C3%ADtulo+y+el+autor+son+obligatorios.&tipo=error', AppClient::landedOn($noTitle));

        $noAuthor = $this->app->post('libro_editar.php', $this->newBook(['autor' => ' ']), true);
        $this->assertMessage('error', 'El título y el autor son obligatorios.', $noAuthor);

        self::assertSame($before, $this->dbState());
    }

    public function test_edit_book_updates_row(): void
    {
        $response = $this->app->post('libro_editar.php', $this->newBook(['id' => '1', 'titulo' => 'Don Quijote']), true);

        $this->assertMessage('ok', 'Libro actualizado.', $response);
        self::assertSame('libros.php?msg=Libro+actualizado.&tipo=ok', AppClient::landedOn($response));
        self::assertSame('Don Quijote', $this->db->fetchValue('SELECT titulo FROM libros WHERE id = 1'));
        self::assertSame(15, $this->rows('libros'));
    }

    public function test_edit_book_requires_title_and_author(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('libro_editar.php', $this->newBook(['id' => '1', 'autor' => '']), true);

        $this->assertMessage('error', 'El título y el autor son obligatorios.', $response);
        self::assertSame('libro_editar.php?id=1&msg=El+t%C3%ADtulo+y+el+autor+son+obligatorios.&tipo=error', AppClient::landedOn($response));
        self::assertSame($before, $this->dbState());
    }

    public function test_unknown_book_redirects_to_list_with_error(): void
    {
        $edit = $this->app->get('libro_editar.php', ['id' => 999], true);
        $this->assertMessage('error', 'El libro no existe.', $edit);
        self::assertSame('libros.php?msg=El+libro+no+existe.&tipo=error', AppClient::landedOn($edit));

        $copies = $this->app->get('ejemplares.php', ['libro_id' => 999], true);
        $this->assertMessage('error', 'El libro no existe.', $copies);
    }

    public function test_book_list_counts_copies_without_the_written_off_ones(): void
    {
        $rows = $this->page($this->app->get('libros.php'))->tableRows();
        $byTitle = array_column($rows, null, 0);

        self::assertCount(15, $rows);
        self::assertSame(['Don Quijote de la Mancha', 'Miguel de Cervantes', '1605', '978-0-0000-0001-1', '3', '2'], array_slice($byTitle['Don Quijote de la Mancha'], 0, 6));
        self::assertSame('0', $byTitle['Cumandá'][4], 'The only copy of Cumandá is written off');
        self::assertSame('0', $byTitle['Cumandá'][5]);
    }

    public function test_add_copy_stores_it_as_available(): void
    {
        $response = $this->app->post('ejemplares.php', ['accion' => 'alta', 'libro_id' => 4, 'codigo' => 'BIB-0100'], true);

        $this->assertMessage('ok', 'Ejemplar agregado.', $response);
        self::assertSame('ejemplares.php?libro_id=4&msg=Ejemplar+agregado.&tipo=ok', AppClient::landedOn($response));
        $row = $this->db->fetchOne("SELECT * FROM ejemplares WHERE codigo = 'BIB-0100'");
        self::assertSame(4, (int) $row['libro_id']);
        self::assertSame('disponible', $row['estado']);
        self::assertSame([['BIB-0008', 'Prestado', '-'], ['BIB-0100', 'Disponible', 'Dar de baja']], $this->page($response)->tableRows());
    }

    public function test_add_copy_requires_a_code(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('ejemplares.php', ['accion' => 'alta', 'libro_id' => 4, 'codigo' => '  '], true);

        $this->assertMessage('error', 'Escribe el código del ejemplar.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_add_copy_rejects_repeated_code(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('ejemplares.php', ['accion' => 'alta', 'libro_id' => 4, 'codigo' => 'BIB-0001'], true);

        $this->assertMessage('error', 'Ya existe un ejemplar con ese código.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_write_off_available_copy(): void
    {
        $response = $this->app->post('ejemplares.php', ['accion' => 'baja', 'libro_id' => 1, 'ejemplar_id' => 2], true);

        $this->assertMessage('ok', 'Ejemplar dado de baja.', $response);
        self::assertSame('baja', $this->db->fetchValue('SELECT estado FROM ejemplares WHERE id = 2'));
        self::assertSame('Baja', $this->page($response)->tableRows()[1][1]);
    }

    public function test_write_off_is_rejected_for_a_loaned_copy(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('ejemplares.php', ['accion' => 'baja', 'libro_id' => 1, 'ejemplar_id' => 1], true);

        $this->assertMessage('error', 'No se puede dar de baja un ejemplar prestado o reservado.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_write_off_is_rejected_for_a_reserved_copy(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('ejemplares.php', ['accion' => 'baja', 'libro_id' => 6, 'ejemplar_id' => 11], true);

        $this->assertMessage('error', 'No se puede dar de baja un ejemplar prestado o reservado.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_write_off_of_a_copy_that_belongs_to_another_book_is_rejected(): void
    {
        $before = $this->dbState();

        $response = $this->app->post('ejemplares.php', ['accion' => 'baja', 'libro_id' => 2, 'ejemplar_id' => 2], true);

        $this->assertMessage('error', 'El ejemplar no existe.', $response);
        self::assertSame($before, $this->dbState());
    }

    public function test_only_available_copies_offer_the_write_off_button(): void
    {
        $html = $this->page($this->app->get('ejemplares.php', ['libro_id' => 1]));

        self::assertSame(
            [['BIB-0001', 'Prestado', '-'], ['BIB-0002', 'Disponible', 'Dar de baja'], ['BIB-0003', 'Disponible', 'Dar de baja']],
            $html->tableRows(),
        );
    }
}
