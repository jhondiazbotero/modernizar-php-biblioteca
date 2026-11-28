<?php

declare(strict_types=1);

namespace Library\Tests\Integration;

use Library\Application\AddCopy;
use Library\Application\Authenticate;
use Library\Application\EditBook;
use Library\Application\EditMember;
use Library\Application\RegisterBook;
use Library\Application\RegisterMember;
use Library\Application\WithdrawCopy;
use Library\Auth\PasswordVerifier;
use Library\Infrastructure\Persistence\PdoBookRepository;
use Library\Infrastructure\Persistence\PdoLibrarianRepository;
use Library\Infrastructure\Persistence\PdoMemberRepository;
use Library\Infrastructure\Persistence\Query\PdoDashboardQuery;
use Library\Infrastructure\Persistence\Query\PdoMemberQuery;
use Library\Infrastructure\Persistence\Query\PdoSearchQuery;
use Library\Tests\Integration\Support\DatabaseTestCase;

/** Members, books, copies, sign-in and the read queries against a real MySQL. */
final class ManagementTest extends DatabaseTestCase
{
    public function test_a_member_is_registered_with_the_date_of_the_clock_and_the_document_is_unique(): void
    {
        $useCase = new RegisterMember($this->transactions(), new PdoMemberRepository($this->pdo), $this->clock());

        $result = $useCase->handle(' 2002 ', ' Elena Prieto ', 'elena@example.com', '300', false);

        self::assertTrue($result->isSuccess());
        self::assertSame('2002', $this->scalar('SELECT documento FROM socios WHERE id = ?', [$result->getId()]));
        self::assertSame('Elena Prieto', $this->scalar('SELECT nombre FROM socios WHERE id = ?', [$result->getId()]));
        self::assertSame('2026-11-27', $this->scalar('SELECT fecha_alta FROM socios WHERE id = ?', [$result->getId()]));
        self::assertEquals(0, $this->scalar('SELECT activo FROM socios WHERE id = ?', [$result->getId()]));

        self::assertSame('document_taken', $useCase->handle('1001', 'Otra Persona', '', '', true)->getErrorCode());
        self::assertEquals(5, $this->scalar('SELECT COUNT(*) FROM socios'));
    }

    public function test_editing_a_member_keeps_its_own_document_and_rejects_another_ones(): void
    {
        $useCase = new EditMember($this->transactions(), new PdoMemberRepository($this->pdo));

        self::assertTrue($useCase->handle(1, '1001', 'Ana María Torres', 'ana@example.com', '311', true)->isSuccess());
        self::assertSame('Ana María Torres', $this->scalar('SELECT nombre FROM socios WHERE id = 1'));
        self::assertSame('2026-01-01', $this->scalar('SELECT fecha_alta FROM socios WHERE id = 1'));

        self::assertSame('document_taken', $useCase->handle(1, '1002', 'Ana', '', '', true)->getErrorCode());
        self::assertSame('1001', $this->scalar('SELECT documento FROM socios WHERE id = 1'));
    }

    public function test_a_book_is_registered_and_edited_with_or_without_year(): void
    {
        $books = new PdoBookRepository($this->pdo);

        $id = (new RegisterBook($this->transactions(), $books))->handle('978-0', 'Ficciones', 'Jorge Luis Borges', null)->getId();
        self::assertSame('Ficciones', $this->scalar('SELECT titulo FROM libros WHERE id = ?', [$id]));
        self::assertNull($this->scalar('SELECT anio FROM libros WHERE id = ?', [$id]));

        (new EditBook($this->transactions(), $books))->handle($id, '978-1', 'Ficciones (1944)', 'J. L. Borges', 1944);
        self::assertSame('Ficciones (1944)', $this->scalar('SELECT titulo FROM libros WHERE id = ?', [$id]));
        self::assertEquals(1944, $this->scalar('SELECT anio FROM libros WHERE id = ?', [$id]));
    }

    public function test_copies_are_added_with_a_unique_code_and_withdrawn_unless_in_use(): void
    {
        $add = new AddCopy($this->transactions(), new PdoBookRepository($this->pdo), $this->copyRepository());
        $withdraw = new WithdrawCopy($this->transactions(), $this->copyRepository());

        $result = $add->handle(5, 'BIB-0099');
        self::assertTrue($result->isSuccess());
        self::assertSame('disponible', $this->scalar('SELECT estado FROM ejemplares WHERE id = ?', [$result->getId()]));
        self::assertSame('code_taken', $add->handle(5, 'BIB-0010')->getErrorCode());
        self::assertSame('book_not_found', $add->handle(99, 'BIB-0100')->getErrorCode());

        self::assertSame('copy_in_use', $withdraw->handle(5, 10)->getErrorCode());
        self::assertSame('copy_not_found', $withdraw->handle(6, 11)->getErrorCode());
        self::assertTrue($withdraw->handle(5, 11)->isSuccess());
        self::assertSame('baja', $this->scalar('SELECT estado FROM ejemplares WHERE id = 11'));
    }

    public function test_signing_in_with_an_md5_account_stores_a_modern_hash(): void
    {
        $this->pdo->exec('DELETE FROM bibliotecarios');
        $this->pdo->exec("INSERT INTO bibliotecarios (id, usuario, clave, nombre) VALUES (1, 'admin', '" . md5('s3cret') . "', 'Administrador')");
        $useCase = new Authenticate(new PdoLibrarianRepository($this->pdo), new PasswordVerifier());

        self::assertSame('invalid_credentials', $useCase->handle('admin', 'other')->getErrorCode());
        self::assertSame(md5('s3cret'), $this->scalar('SELECT clave FROM bibliotecarios WHERE id = 1'));

        $result = $useCase->handle('admin', 's3cret');
        self::assertTrue($result->isSuccess());
        self::assertSame('Administrador', $result->getLibrarianName());
        $stored = (string) $this->scalar('SELECT clave FROM bibliotecarios WHERE id = 1');
        self::assertStringStartsWith('$2y$', $stored);
        self::assertTrue(password_verify('s3cret', $stored));
    }

    public function test_the_search_treats_wildcards_as_text(): void
    {
        $this->pdo->exec("INSERT INTO libros (id, titulo, autor, anio) VALUES (6, '100% real', 'Autor Uno', 2001), (7, 'Cien', 'Autor Dos', NULL)");
        $query = new PdoSearchQuery($this->pdo);

        self::assertSame([6], array_column($query->booksMatching('100%'), 'id'));
        self::assertSame([], $query->booksMatching('_ien'));
        self::assertSame([5], array_column($query->booksMatching('rayuela'), 'id'));
        self::assertSame(1, $query->booksMatching('Rayuela')[0]['available']);
    }

    public function test_the_member_detail_lists_loans_and_the_unpaid_fines(): void
    {
        $this->pdo->exec("INSERT INTO prestamos (ejemplar_id, socio_id, fecha_prestamo, fecha_vence, fecha_devolucion, multa, multa_pagada) VALUES (11, 1, '2026-09-01', '2026-09-15', '2026-09-21', 3000, 0), (11, 1, '2026-08-01', '2026-08-15', '2026-08-16', 500, 1)");
        $query = new PdoMemberQuery($this->pdo);

        self::assertEqualsWithDelta(3000.0, $query->unpaidFineTotal(1), 0.001);
        self::assertSame(0.0, $query->unpaidFineTotal(2));
        $loans = $query->loansOf(1);
        self::assertSame(['2026-11-01', '2026-09-01', '2026-08-01'], array_column($loans, 'loaned_on'));
        self::assertNull($loans[0]['returned_on']);
        self::assertSame('2026-09-21', $loans[1]['returned_on']);
    }

    public function test_the_dashboard_counts_overdue_loans_by_the_given_day(): void
    {
        $query = new PdoDashboardQuery($this->pdo);

        $before = $query->summary('2026-11-14');
        $after = $query->summary('2026-11-15');

        self::assertSame(1, $before['active_loans']);
        self::assertSame(0, $before['overdue_count']);
        self::assertSame(1, $after['overdue_count']);
        self::assertSame('Ana Torres', $after['overdue'][0]['member']);
        self::assertSame('BIB-0010', $after['overdue'][0]['code']);
    }
}
