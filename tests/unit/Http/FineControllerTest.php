<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\PayFine;
use Library\Http\Controller\FineController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeFineQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\InMemoryLoanRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class FineControllerTest extends TestCase
{
    /** @var InMemoryStore */
    private $store;

    /** @var FakeFineQuery */
    private $query;

    protected function setUp(): void
    {
        $this->store = new InMemoryStore();
        $this->store->addMember(1, 'Ana Torres');
        $this->store->addCopy(10, 5, 'Rayuela');
        $this->query = new FakeFineQuery();
    }

    private function controller(): FineController
    {
        return new FineController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->query,
            new PayFine(new FakeTransactionManager($this->store), new InMemoryLoanRepository($this->store))
        );
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    public function test_paying_a_fine_says_how_much(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-14', 6500, false);

        $response = $this->controller()->index(new Request('POST', '/multas.php', [], ['prestamo_id' => '1']), $this->session());

        self::assertSame('multas.php?msg=' . urlencode('Multa pagada: $ 6.500.') . '&tipo=ok', $response->getHeader('Location'));
        self::assertTrue($this->store->loans[1]['paid']);
    }

    public function test_the_errors_have_their_texts(): void
    {
        $this->store->addLoan(1, 10, 1, '2026-11-01', '2026-11-14', 6500, true);
        $this->store->addLoan(2, 10, 1, '2026-11-01', '2026-11-01', 0, false);

        $cases = [
            '99' => 'El préstamo no existe.',
            '2' => 'Ese préstamo no tiene multa.',
            '1' => 'Esa multa ya estaba pagada.',
            '' => 'El préstamo no existe.',
        ];
        foreach ($cases as $id => $text) {
            $response = $this->controller()->index(new Request('POST', '/multas.php', [], ['prestamo_id' => (string) $id]), $this->session());

            self::assertSame('multas.php?msg=' . urlencode($text) . '&tipo=error', $response->getHeader('Location'), 'loan ' . $id);
        }
    }

    public function test_the_list_shows_paid_and_unpaid_fines(): void
    {
        $this->query->fines = [
            ['id' => 3, 'member_id' => 7, 'member' => 'Ana <b>Torres</b>', 'title' => 'Rayuela', 'due_date' => '2026-09-15', 'returned_on' => '2026-09-21', 'fine' => 3000.0, 'paid' => false],
            ['id' => 4, 'member_id' => 8, 'member' => 'Luis Pérez', 'title' => 'Emma', 'due_date' => '2026-08-15', 'returned_on' => '2026-08-16', 'fine' => 500.0, 'paid' => true],
        ];

        $body = $this->controller()->index(new Request('GET', '/multas.php'), $this->session())->getBody();

        self::assertStringContainsString('<a href="socio_ver.php?id=7">Ana &lt;b&gt;Torres&lt;/b&gt;</a>', $body);
        self::assertStringContainsString('<td class="num">$ 3.000</td>', $body);
        self::assertStringContainsString('<td>21/09/2026</td>', $body);
        self::assertSame(1, substr_count($body, 'Pagar multa</button>'));
        self::assertStringContainsString('<input type="hidden" name="prestamo_id" value="3">', $body);
        self::assertSame(1, preg_match('/<td>\s*Pagada\s*<\/td>/', $body));
    }

    public function test_the_empty_list_says_so(): void
    {
        $body = $this->controller()->index(new Request('GET', '/multas.php'), $this->session())->getBody();

        self::assertStringContainsString('No hay multas registradas.', $body);
    }
}
