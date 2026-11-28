<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Controller\SearchController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeSearchQuery;
use PHPUnit\Framework\TestCase;

final class SearchControllerTest extends TestCase
{
    /** @var FakeSearchQuery */
    private $query;

    protected function setUp(): void
    {
        $this->query = new FakeSearchQuery();
    }

    private function body(Request $request): string
    {
        $controller = new SearchController(new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')), $this->query);
        $session = Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);

        return $controller->index($request, $session)->getBody();
    }

    public function test_without_text_only_the_form_is_shown_and_nothing_is_searched(): void
    {
        $body = $this->body(new Request('GET', '/buscar.php'));

        self::assertStringContainsString('<h2>Buscar libros</h2>', $body);
        self::assertStringContainsString('<input type="text" id="q" name="q" value="">', $body);
        self::assertStringNotContainsString('resultado(s)', $body);
        self::assertStringNotContainsString('No se encontraron', $body);
        self::assertSame([], $this->query->searched);
    }

    public function test_a_text_that_is_not_text_counts_as_no_text(): void
    {
        $this->body(new Request('GET', '/buscar.php', ['q' => ['x']]));

        self::assertSame([], $this->query->searched);
    }

    public function test_the_text_is_passed_to_the_query_as_the_user_typed_it(): void
    {
        $this->body(new Request('GET', '/buscar.php', ['q' => "' OR 1=1 -- 100%_\\"]));

        self::assertSame(["' OR 1=1 -- 100%_\\"], $this->query->searched);
    }

    public function test_no_results_repeats_the_text_escaped(): void
    {
        $body = $this->body(new Request('GET', '/buscar.php', ['q' => '<script>"x"</script>']));

        self::assertStringContainsString('No se encontraron libros para «&lt;script&gt;&quot;x&quot;&lt;/script&gt;».', $body);
        self::assertStringContainsString('value="&lt;script&gt;&quot;x&quot;&lt;/script&gt;"', $body);
        self::assertStringNotContainsString('<script>', $body);
    }

    public function test_results_are_counted_and_each_book_links_to_its_copies(): void
    {
        $this->query->books = [
            ['id' => 3, 'title' => 'Don Quijote <1>', 'author' => 'Cervantes', 'year' => 1605, 'available' => 2],
            ['id' => 4, 'title' => 'Otro', 'author' => 'Alguien', 'year' => null, 'available' => 0],
        ];

        $body = $this->body(new Request('GET', '/buscar.php', ['q' => 'Quijote']));

        self::assertStringContainsString('<p>2 resultado(s).</p>', $body);
        self::assertStringContainsString('<td>Don Quijote &lt;1&gt;</td>', $body);
        self::assertStringContainsString('<td>1605</td>', $body);
        self::assertStringContainsString('<td class="num">2</td>', $body);
        self::assertStringContainsString('<a href="ejemplares.php?libro_id=4">Ejemplares</a>', $body);
    }
}
