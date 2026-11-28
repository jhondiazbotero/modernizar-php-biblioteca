<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\EditBook;
use Library\Application\RegisterBook;
use Library\Http\Controller\BookController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeBookQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\InMemoryBookRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class BookControllerTest extends TestCase
{
    /** @var FakeBookQuery */
    private $query;

    /** @var InMemoryStore */
    private $store;

    protected function setUp(): void
    {
        $this->query = new FakeBookQuery();
        $this->store = new InMemoryStore();
    }

    private function controller(): BookController
    {
        $transactions = new FakeTransactionManager($this->store);
        $books = new InMemoryBookRepository($this->store);

        return new BookController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->query,
            new RegisterBook($transactions, $books),
            new EditBook($transactions, $books)
        );
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + ['id' => '0', 'isbn' => '978-0', 'titulo' => 'Rayuela', 'autor' => 'Julio Cortázar', 'anio' => '1963'];
    }

    public function test_the_list_prints_titles_authors_and_counts_escaped(): void
    {
        $this->query->addBook(1, '<i>Rayuela</i>', 'Cortázar & Cía', ['year' => 1963, 'total' => 3, 'available' => 1, 'isbn' => '978-0']);
        $this->query->addBook(2, 'Emma', 'Jane Austen');

        $body = $this->controller()->index(new Request('GET', '/libros.php'), $this->session())->getBody();

        self::assertStringContainsString('<td>&lt;i&gt;Rayuela&lt;/i&gt;</td>', $body);
        self::assertStringContainsString('<td>Cortázar &amp; Cía</td>', $body);
        self::assertStringContainsString('<td>1963</td>', $body);
        self::assertStringContainsString('<td class="num">3</td>', $body);
        self::assertStringContainsString('<a href="ejemplares.php?libro_id=2">Ejemplares</a>', $body);
        self::assertStringContainsString('<td></td>', $body, 'A book without a year leaves the cell empty');
    }

    public function test_the_empty_form_is_for_a_new_book(): void
    {
        $body = $this->controller()->edit(new Request('GET', '/libro_editar.php'), $this->session())->getBody();

        self::assertStringContainsString('<h2>Nuevo libro</h2>', $body);
        self::assertStringContainsString('<input type="hidden" name="id" value="0">', $body);
        self::assertStringContainsString('<input type="number" id="anio" name="anio" value="">', $body);
    }

    public function test_the_form_of_a_book_is_filled_and_escaped(): void
    {
        $this->query->addBook(4, 'El "libro" <raro>', 'Autor', ['year' => 1999, 'isbn' => '12-3']);

        $body = $this->controller()->edit(new Request('GET', '/libro_editar.php', ['id' => '4']), $this->session())->getBody();

        self::assertStringContainsString('<h2>Editar libro</h2>', $body);
        self::assertStringContainsString('value="El &quot;libro&quot; &lt;raro&gt;"', $body);
        self::assertStringContainsString('name="anio" value="1999"', $body);
    }

    public function test_the_form_of_an_unknown_book_goes_back_to_the_list_with_an_error(): void
    {
        $response = $this->controller()->edit(new Request('GET', '/libro_editar.php', ['id' => '99']), $this->session());

        self::assertSame('libros.php?msg=El+libro+no+existe.&tipo=error', $response->getHeader('Location'));
    }

    public function test_a_new_book_sends_the_user_to_add_copies(): void
    {
        $response = $this->controller()->edit(new Request('POST', '/libro_editar.php', [], $this->form()), $this->session());

        self::assertSame(
            'ejemplares.php?libro_id=1&msg=Libro+registrado.+Ahora+puedes+agregar+sus+ejemplares.&tipo=ok',
            $response->getHeader('Location')
        );
        self::assertSame(1963, $this->store->bookData[1]['year']);
    }

    public function test_an_empty_year_is_stored_as_no_year(): void
    {
        $this->controller()->edit(new Request('POST', '/libro_editar.php', [], $this->form(['anio' => ''])), $this->session());

        self::assertNull($this->store->bookData[1]['year']);
    }

    public function test_an_existing_book_is_updated(): void
    {
        $this->store->addBook(3, 'Viejo', 'Autor');

        $response = $this->controller()->edit(new Request('POST', '/libro_editar.php', [], $this->form(['id' => '3'])), $this->session());

        self::assertSame('libros.php?msg=Libro+actualizado.&tipo=ok', $response->getHeader('Location'));
        self::assertSame('Rayuela', $this->store->bookData[3]['title']);
    }

    public function test_missing_title_goes_back_to_the_form_with_the_error(): void
    {
        $new = $this->controller()->edit(new Request('POST', '/libro_editar.php', [], $this->form(['titulo' => ' '])), $this->session());
        $edit = $this->controller()->edit(new Request('POST', '/libro_editar.php', [], $this->form(['id' => '3', 'autor' => ''])), $this->session());

        self::assertSame('libro_editar.php?msg=El+t%C3%ADtulo+y+el+autor+son+obligatorios.&tipo=error', $new->getHeader('Location'));
        self::assertSame('libro_editar.php?id=3&msg=El+t%C3%ADtulo+y+el+autor+son+obligatorios.&tipo=error', $edit->getHeader('Location'));
        self::assertSame([], $this->store->bookData);
    }
}
