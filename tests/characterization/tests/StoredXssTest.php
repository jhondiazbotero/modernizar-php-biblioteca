<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\Group;

final class StoredXssTest extends CharacterizationTestCase
{
    private const NAME = '<b>Ana</b>';

    private function registerMemberWithMarkupInName(): int
    {
        $this->app->post('socio_editar.php', [
            'id' => '0',
            'documento' => '3003003001',
            'nombre' => self::NAME,
            'correo' => 'ana@example.com',
            'telefono' => '3001234567',
            'activo' => '1',
        ]);

        return (int) $this->db->fetchValue('SELECT id FROM socios WHERE documento = ?', ['3003003001']);
    }

    /**
     * Current behavior (known defect F4): the member list prints the name without escaping, so the markup of the name becomes part of the page.
     */
    #[Group('known-defect')]
    public function test_f4_member_list_prints_the_name_without_escaping(): void
    {
        $this->registerMemberWithMarkupInName();

        $html = $this->page($this->app->get('socios.php'));

        self::assertSame(self::NAME, $this->db->fetchValue('SELECT nombre FROM socios WHERE documento = ?', ['3003003001']));
        self::assertSame(1, $html->count('//td/b[text()="Ana"]'), 'The <b> tag of the name is live in the list');
        self::assertStringContainsString('<td><b>Ana</b></td>', (string) $this->app->get('socios.php')->getBody());
    }

    public function test_member_detail_prints_the_name_escaped(): void
    {
        $id = $this->registerMemberWithMarkupInName();

        $response = $this->app->get('socio_ver.php', ['id' => $id]);
        $html = $this->page($response);

        self::assertSame(self::NAME, $html->heading());
        self::assertSame(0, $html->count('//h2/b'));
        self::assertStringContainsString('<h2>&lt;b&gt;Ana&lt;/b&gt;</h2>', (string) $response->getBody());
    }

    public function test_other_screens_print_the_name_escaped(): void
    {
        $this->registerMemberWithMarkupInName();

        foreach (['prestar.php', 'reservas.php'] as $path) {
            $body = (string) $this->app->get($path)->getBody();
            self::assertStringContainsString('&lt;b&gt;Ana&lt;/b&gt;', $body, $path);
            self::assertStringNotContainsString('<b>Ana</b>', $body, $path);
        }
    }
}
