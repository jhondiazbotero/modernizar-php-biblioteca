<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function test_it_exposes_method_path_and_headers(): void
    {
        $request = new Request('POST', '/socios.php', [], [], ['X-Fecha-Fija' => '2026-04-15']);

        self::assertSame('POST', $request->getMethod());
        self::assertTrue($request->isPost());
        self::assertSame('/socios.php', $request->getPath());
        self::assertSame('2026-04-15', $request->getHeader('x-fecha-fija'));
        self::assertSame('2026-04-15', $request->getHeader('X-FECHA-FIJA'));
        self::assertNull($request->getHeader('X-Other'));
        self::assertSame(['x-fecha-fija' => '2026-04-15'], $request->getHeaders());
    }

    public function test_only_post_is_a_post(): void
    {
        self::assertFalse((new Request('GET', '/'))->isPost());
        self::assertFalse((new Request('HEAD', '/'))->isPost());
        self::assertFalse((new Request('post', '/'))->isPost());
    }

    public function test_text_parameters_come_back_as_text_or_the_default(): void
    {
        $request = new Request('GET', '/', ['q' => 'Quijote', 'list' => ['x'], 'n' => '5'], ['name' => ' Ana ', 'tags' => ['a']]);

        self::assertSame('Quijote', $request->queryString('q'));
        self::assertSame('', $request->queryString('list'));
        self::assertSame('activos', $request->queryString('missing', 'activos'));
        self::assertSame(' Ana ', $request->postString('name'));
        self::assertSame('', $request->postString('tags'));
        self::assertSame('x', $request->postString('missing', 'x'));
    }

    public function test_integers_are_read_like_a_cast(): void
    {
        $request = new Request('POST', '/', ['id' => '12abc', 'bad' => 'abc', 'list' => ['1']], ['id' => '7', 'float' => '3.9']);

        self::assertSame(12, $request->queryInt('id'));
        self::assertSame(0, $request->queryInt('bad'));
        self::assertSame(0, $request->queryInt('list'));
        self::assertSame(0, $request->queryInt('missing'));
        self::assertSame(7, $request->postInt('id'));
        self::assertSame(3, $request->postInt('float'));
        self::assertSame(0, $request->postInt('missing'));
    }

    public function test_it_knows_which_form_fields_were_sent(): void
    {
        $request = new Request('POST', '/', [], ['activo' => '1', 'empty' => '']);

        self::assertTrue($request->hasPost('activo'));
        self::assertTrue($request->hasPost('empty'));
        self::assertFalse($request->hasPost('other'));
    }

    public function test_the_path_can_be_missing(): void
    {
        self::assertNull((new Request('GET', null))->getPath());
    }
}
