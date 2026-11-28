<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\AddCopy;
use Library\Application\WithdrawCopy;
use Library\Domain\CopySnapshot;
use Library\Http\Controller\CopyController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeBookQuery;
use Library\Tests\Fake\FakeCopyQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\InMemoryBookRepository;
use Library\Tests\Fake\InMemoryCopyRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class CopyControllerTest extends TestCase
{
    /** @var FakeBookQuery */
    private $books;

    /** @var FakeCopyQuery */
    private $copyQuery;

    /** @var InMemoryStore */
    private $store;

    protected function setUp(): void
    {
        $this->books = new FakeBookQuery();
        $this->books->addBook(5, 'Rayuela', 'Julio Cortázar');
        $this->copyQuery = new FakeCopyQuery();
        $this->store = new InMemoryStore();
        $this->store->addBook(5, 'Rayuela', 'Julio Cortázar');
    }

    private function controller(): CopyController
    {
        $transactions = new FakeTransactionManager($this->store);
        $copies = new InMemoryCopyRepository($this->store);

        return new CopyController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->books,
            $this->copyQuery,
            new AddCopy($transactions, new InMemoryBookRepository($this->store), $copies),
            new WithdrawCopy($transactions, $copies)
        );
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    public function test_the_list_shows_the_copies_and_only_offers_to_withdraw_available_ones(): void
    {
        $this->copyQuery->add(5, 10, 'BIB-1', 'disponible');
        $this->copyQuery->add(5, 11, 'BIB-<2>', 'prestado');

        $body = $this->controller()->index(new Request('GET', '/ejemplares.php', ['libro_id' => '5']), $this->session())->getBody();

        self::assertStringContainsString('<h2>Ejemplares: Rayuela</h2>', $body);
        self::assertStringContainsString('<title>Ejemplares de Rayuela - Biblioteca comunitaria</title>', $body);
        self::assertStringContainsString('<td>Disponible</td>', $body);
        self::assertStringContainsString('<td>Prestado</td>', $body);
        self::assertStringContainsString('<td>BIB-&lt;2&gt;</td>', $body);
        self::assertSame(1, substr_count($body, 'Dar de baja</button>'));
        self::assertStringContainsString('<input type="hidden" name="ejemplar_id" value="10">', $body);
    }

    public function test_an_unknown_book_goes_back_to_the_list_of_books(): void
    {
        $response = $this->controller()->index(new Request('GET', '/ejemplares.php', ['libro_id' => '99']), $this->session());

        self::assertSame('libros.php?msg=El+libro+no+existe.&tipo=error', $response->getHeader('Location'));
    }

    public function test_the_book_of_a_post_is_the_one_in_the_form(): void
    {
        $response = $this->controller()->index(
            new Request('POST', '/ejemplares.php', ['libro_id' => '99'], ['libro_id' => '5', 'accion' => 'alta', 'codigo' => 'BIB-9']),
            $this->session()
        );

        self::assertSame('ejemplares.php?libro_id=5&msg=Ejemplar+agregado.&tipo=ok', $response->getHeader('Location'));
    }

    public function test_a_copy_is_added(): void
    {
        $response = $this->controller()->index(
            new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'alta', 'codigo' => ' BIB-9 ']),
            $this->session()
        );

        self::assertSame('ejemplares.php?libro_id=5&msg=Ejemplar+agregado.&tipo=ok', $response->getHeader('Location'));
        self::assertSame('BIB-9', $this->store->copyCodes[1]);
    }

    public function test_an_empty_or_repeated_code_is_reported(): void
    {
        $this->store->addCopy(20, 5, 'Rayuela');
        $this->store->copyCodes[20] = 'BIB-1';

        $empty = $this->controller()->index(new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'alta', 'codigo' => '']), $this->session());
        $taken = $this->controller()->index(new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'alta', 'codigo' => 'BIB-1']), $this->session());

        self::assertSame('ejemplares.php?libro_id=5&msg=Escribe+el+c%C3%B3digo+del+ejemplar.&tipo=error', $empty->getHeader('Location'));
        self::assertSame('ejemplares.php?libro_id=5&msg=Ya+existe+un+ejemplar+con+ese+c%C3%B3digo.&tipo=error', $taken->getHeader('Location'));
        self::assertCount(1, $this->store->copies);
    }

    public function test_an_available_copy_is_withdrawn(): void
    {
        $this->store->addCopy(20, 5, 'Rayuela');

        $response = $this->controller()->index(
            new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'baja', 'ejemplar_id' => '20']),
            $this->session()
        );

        self::assertSame('ejemplares.php?libro_id=5&msg=Ejemplar+dado+de+baja.&tipo=ok', $response->getHeader('Location'));
        self::assertSame(CopySnapshot::WITHDRAWN, $this->store->copyStatus(20));
    }

    public function test_a_copy_on_loan_is_not_withdrawn_and_an_unknown_one_is_reported(): void
    {
        $this->store->addCopy(20, 5, 'Rayuela', CopySnapshot::ON_LOAN);

        $inUse = $this->controller()->index(new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'baja', 'ejemplar_id' => '20']), $this->session());
        $unknown = $this->controller()->index(new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'baja', 'ejemplar_id' => '77']), $this->session());

        self::assertSame(
            'ejemplares.php?libro_id=5&msg=No+se+puede+dar+de+baja+un+ejemplar+prestado+o+reservado.&tipo=error',
            $inUse->getHeader('Location')
        );
        self::assertSame('ejemplares.php?libro_id=5&msg=El+ejemplar+no+existe.&tipo=error', $unknown->getHeader('Location'));
        self::assertSame(CopySnapshot::ON_LOAN, $this->store->copyStatus(20));
    }

    public function test_a_post_with_an_unknown_action_just_shows_the_list(): void
    {
        $response = $this->controller()->index(new Request('POST', '/ejemplares.php', [], ['libro_id' => '5', 'accion' => 'otra']), $this->session());

        self::assertSame(200, $response->getStatus());
        self::assertStringContainsString('<h2>Ejemplares: Rayuela</h2>', $response->getBody());
    }
}
