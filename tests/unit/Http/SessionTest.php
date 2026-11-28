<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\Session;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    public function test_values_are_stored_and_read(): void
    {
        $session = Session::inMemory(['librarian_id' => 3]);

        self::assertTrue($session->has('librarian_id'));
        self::assertSame(3, $session->get('librarian_id'));
        self::assertFalse($session->has('other'));
        self::assertNull($session->get('other'));
        self::assertSame('x', $session->get('other', 'x'));

        $session->set('other', 'y');
        self::assertSame('y', $session->get('other'));
    }

    public function test_regenerating_and_destroying_are_recorded(): void
    {
        $session = Session::inMemory();
        self::assertSame(0, $session->regenerationCount());
        self::assertFalse($session->isDestroyed());

        $session->regenerate();
        $session->destroy();

        self::assertSame(1, $session->regenerationCount());
        self::assertTrue($session->isDestroyed());
    }

    public function test_starting_an_in_memory_session_changes_nothing(): void
    {
        $session = Session::inMemory(['a' => 1]);

        $session->start();

        self::assertSame(1, $session->get('a'));
    }
}
