<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Application\EditMember;
use Library\Application\RegisterMember;
use Library\Http\Controller\MemberController;
use Library\Http\PageRenderer;
use Library\Http\Request;
use Library\Http\Session;
use Library\Http\View;
use Library\Tests\Fake\FakeMemberQuery;
use Library\Tests\Fake\FakeTransactionManager;
use Library\Tests\Fake\FixedClock;
use Library\Tests\Fake\InMemoryMemberRepository;
use Library\Tests\Fake\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class MemberControllerTest extends TestCase
{
    /** @var FakeMemberQuery */
    private $query;

    /** @var InMemoryStore */
    private $store;

    protected function setUp(): void
    {
        $this->query = new FakeMemberQuery();
        $this->store = new InMemoryStore();
    }

    private function controller(): MemberController
    {
        $clock = new FixedClock('2026-11-27 12:00:00');
        $transactions = new FakeTransactionManager($this->store);
        $members = new InMemoryMemberRepository($this->store);

        return new MemberController(
            new PageRenderer(new View(dirname(__DIR__, 3) . '/templates')),
            $this->query,
            new RegisterMember($transactions, $members, $clock),
            new EditMember($transactions, $members),
            $clock
        );
    }

    /**
     * @param array<string, string> $overrides
     *
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return $overrides + ['id' => '0', 'documento' => '2002002001', 'nombre' => 'Elena Prieto Luna', 'correo' => 'elena@example.com', 'telefono' => '3009998877', 'activo' => '1'];
    }

    private function session(): Session
    {
        return Session::inMemory([Session::LIBRARIAN_ID => 1, Session::LIBRARIAN_NAME => 'Administrador']);
    }

    public function test_the_list_prints_the_name_escaped(): void
    {
        $this->query->addMember(1, '<b>Ana</b>');

        $body = $this->controller()->index(new Request('GET', '/socios.php'), $this->session())->getBody();

        self::assertStringContainsString('<td>&lt;b&gt;Ana&lt;/b&gt;</td>', $body);
        self::assertStringNotContainsString('<b>Ana</b>', $body);
    }

    public function test_the_list_marks_inactive_members_and_formats_the_date(): void
    {
        $this->query->addMember(1, 'Ana Torres');
        $this->query->addMember(2, 'Luis Pérez', ['active' => false, 'registered_on' => '2025-02-10']);

        $body = $this->controller()->index(new Request('GET', '/socios.php'), $this->session())->getBody();

        self::assertSame(1, substr_count($body, '<tr class="inactivo">'));
        self::assertStringContainsString('<td>Inactivo</td>', $body);
        self::assertStringContainsString('<td>10/02/2025</td>', $body);
        self::assertStringContainsString('<a href="socio_ver.php?id=2">Ver</a>', $body);
    }

    public function test_the_detail_of_an_unknown_member_goes_back_to_the_list_with_an_error(): void
    {
        $response = $this->controller()->show(new Request('GET', '/socio_ver.php', ['id' => '999']), $this->session());

        self::assertSame(302, $response->getStatus());
        self::assertSame('socios.php?msg=El+socio+no+existe.&tipo=error', $response->getHeader('Location'));
    }

    public function test_the_detail_without_an_id_is_an_unknown_member(): void
    {
        $response = $this->controller()->show(new Request('GET', '/socio_ver.php'), $this->session());

        self::assertSame('socios.php?msg=El+socio+no+existe.&tipo=error', $response->getHeader('Location'));
    }

    public function test_the_detail_prints_the_name_escaped_in_title_and_heading(): void
    {
        $this->query->addMember(3, '<b>Ana</b>');

        $body = $this->controller()->show(new Request('GET', '/socio_ver.php', ['id' => '3']), $this->session())->getBody();

        self::assertStringContainsString('<title>Socio &lt;b&gt;Ana&lt;/b&gt; - Biblioteca comunitaria</title>', $body);
        self::assertStringContainsString('<h2>&lt;b&gt;Ana&lt;/b&gt;</h2>', $body);
    }

    public function test_the_detail_without_loans_or_fines(): void
    {
        $this->query->addMember(3, 'Ana Torres');

        $body = $this->controller()->show(new Request('GET', '/socio_ver.php', ['id' => '3']), $this->session())->getBody();

        self::assertStringContainsString('Este socio todavía no tiene préstamos.', $body);
        self::assertStringContainsString('El socio no tiene multas pendientes.', $body);
    }

    public function test_the_detail_shows_loans_with_overdue_and_fines(): void
    {
        $this->query->addMember(3, 'Ana Torres');
        $this->query->loans[3] = [
            ['title' => 'Rayuela', 'code' => 'BIB-1', 'loaned_on' => '2026-11-01', 'due_date' => '2026-11-15', 'returned_on' => null, 'fine' => 0.0, 'fine_paid' => false],
            ['title' => 'Emma', 'code' => 'BIB-2', 'loaned_on' => '2026-09-01', 'due_date' => '2026-09-15', 'returned_on' => '2026-09-21', 'fine' => 3000.0, 'fine_paid' => false],
            ['title' => 'Ficciones', 'code' => 'BIB-3', 'loaned_on' => '2026-11-20', 'due_date' => '2026-12-04', 'returned_on' => null, 'fine' => 0.0, 'fine_paid' => false],
            ['title' => 'Pedro Páramo', 'code' => 'BIB-4', 'loaned_on' => '2026-08-01', 'due_date' => '2026-08-15', 'returned_on' => '2026-08-16', 'fine' => 500.0, 'fine_paid' => true],
        ];
        $this->query->unpaid[3] = 3000.0;

        $body = $this->controller()->show(new Request('GET', '/socio_ver.php', ['id' => '3']), $this->session())->getBody();

        self::assertSame(1, substr_count($body, ' class="vencido"'), 'Only the open loan that is past its date is overdue');
        self::assertStringContainsString('<td class="vencido">15/11/2026</td>', $body);
        self::assertStringContainsString('<td>Sin devolver</td>', $body);
        self::assertStringContainsString('<td>21/09/2026</td>', $body);
        self::assertStringContainsString('$ 3.000 (pendiente)', $body);
        self::assertStringContainsString('$ 500 (pagada)', $body);
        self::assertStringContainsString('Multas pendientes de pago: <strong>$ 3.000</strong>', $body);
    }

    public function test_the_page_shows_the_menu_with_the_librarian_name(): void
    {
        $this->query->addMember(1, 'Ana Torres');

        $body = $this->controller()->index(new Request('GET', '/socios.php'), $this->session())->getBody();

        self::assertStringContainsString('<li class="salir"><a href="logout.php">Salir (Administrador)</a></li>', $body);
    }

    public function test_the_empty_form_is_for_a_new_active_member(): void
    {
        $body = $this->controller()->edit(new Request('GET', '/socio_editar.php'), $this->session())->getBody();

        self::assertStringContainsString('<h2>Nuevo socio</h2>', $body);
        self::assertStringContainsString('<title>Nuevo socio - Biblioteca comunitaria</title>', $body);
        self::assertStringContainsString('<input type="hidden" name="id" value="0">', $body);
        self::assertStringContainsString('name="activo" value="1" checked>', $body);
    }

    public function test_the_form_of_a_member_is_filled_and_escaped(): void
    {
        $this->query->addMember(2, 'Luis "El Largo" <Pérez>', ['active' => false]);

        $body = $this->controller()->edit(new Request('GET', '/socio_editar.php', ['id' => '2']), $this->session())->getBody();

        self::assertStringContainsString('<h2>Editar socio</h2>', $body);
        self::assertStringContainsString('<input type="hidden" name="id" value="2">', $body);
        self::assertStringContainsString('value="Luis &quot;El Largo&quot; &lt;Pérez&gt;"', $body);
        self::assertStringNotContainsString(' checked>', $body);
    }

    public function test_the_form_of_an_unknown_member_goes_back_to_the_list_with_an_error(): void
    {
        $response = $this->controller()->edit(new Request('GET', '/socio_editar.php', ['id' => '999']), $this->session());

        self::assertSame('socios.php?msg=El+socio+no+existe.&tipo=error', $response->getHeader('Location'));
    }

    public function test_a_new_member_is_saved_and_the_list_says_so(): void
    {
        $response = $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $this->form()), $this->session());

        self::assertSame('socios.php?msg=Socio+registrado.&tipo=ok', $response->getHeader('Location'));
        self::assertSame('Elena Prieto Luna', $this->store->members[1]->getName());
    }

    public function test_a_member_without_the_active_box_is_saved_inactive(): void
    {
        $form = $this->form();
        unset($form['activo']);

        $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $form), $this->session());

        self::assertFalse($this->store->members[1]->isActive());
    }

    public function test_an_existing_member_is_updated(): void
    {
        $this->store->addMember(2, 'Luis Pérez', true, '1001001002');

        $response = $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $this->form(['id' => '2', 'documento' => '1001001002'])), $this->session());

        self::assertSame('socios.php?msg=Socio+actualizado.&tipo=ok', $response->getHeader('Location'));
        self::assertSame('Elena Prieto Luna', $this->store->members[2]->getName());
    }

    public function test_missing_fields_go_back_to_the_new_form_with_the_error(): void
    {
        $response = $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $this->form(['documento' => ''])), $this->session());

        self::assertSame('socio_editar.php?msg=El+documento+y+el+nombre+son+obligatorios.&tipo=error', $response->getHeader('Location'));
        self::assertSame([], $this->store->members);
    }

    public function test_a_repeated_document_goes_back_to_the_form_of_that_member_with_the_error(): void
    {
        $this->store->addMember(1, 'Ana Torres', true, '1001001001');
        $this->store->addMember(2, 'Luis Pérez', true, '1001001002');

        $response = $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $this->form(['id' => '2', 'documento' => '1001001001'])), $this->session());

        self::assertSame('socio_editar.php?id=2&msg=Ya+existe+un+socio+con+ese+documento.&tipo=error', $response->getHeader('Location'));
        self::assertSame('Luis Pérez', $this->store->members[2]->getName());
    }

    public function test_a_repeated_document_of_a_new_member_is_rejected(): void
    {
        $this->store->addMember(1, 'Ana Torres', true, '2002002001');

        $response = $this->controller()->edit(new Request('POST', '/socio_editar.php', [], $this->form()), $this->session());

        self::assertSame('socio_editar.php?msg=Ya+existe+un+socio+con+ese+documento.&tipo=error', $response->getHeader('Location'));
        self::assertCount(1, $this->store->members);
    }
}
