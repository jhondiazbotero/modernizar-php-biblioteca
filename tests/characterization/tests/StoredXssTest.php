<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;

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
     * F4 fixed: the member list prints the name escaped, so the markup of the name is text, not part of the page.
     */
    public function test_member_list_prints_the_name_escaped(): void
    {
        $this->registerMemberWithMarkupInName();

        $response = $this->app->get('socios.php');
        $html = $this->page($response);

        self::assertSame(self::NAME, $this->db->fetchValue('SELECT nombre FROM socios WHERE documento = ?', ['3003003001']));
        self::assertSame(0, $html->count('//td/b'), 'The <b> tag of the name must not be live in the list');
        self::assertStringContainsString('<td>&lt;b&gt;Ana&lt;/b&gt;</td>', (string) $response->getBody());
        self::assertStringNotContainsString('<b>Ana</b>', (string) $response->getBody());
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
