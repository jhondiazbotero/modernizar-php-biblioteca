<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class ScreenSnapshotsTest extends CharacterizationTestCase
{
    private const LATE = '2026-12-01 10:00:00';

    /**
     * Snapshot name => [path, query, fixed date (null for the default one)].
     *
     * @return iterable<string, array{string, array<string, string>, ?string}>
     */
    public static function screens(): iterable
    {
        yield 'dashboard' => ['index.php', [], self::LATE];
        yield 'dashboard_without_overdue' => ['index.php', [], null];
        yield 'members' => ['socios.php', [], null];
        yield 'member_new' => ['socio_editar.php', [], null];
        yield 'member_edit' => ['socio_editar.php', ['id' => '1'], null];
        yield 'member_detail_with_fine' => ['socio_ver.php', ['id' => '7'], null];
        yield 'member_detail_with_overdue_loan' => ['socio_ver.php', ['id' => '8'], self::LATE];
        yield 'books' => ['libros.php', [], null];
        yield 'book_new' => ['libro_editar.php', [], null];
        yield 'book_edit' => ['libro_editar.php', ['id' => '1'], null];
        yield 'copies_of_book' => ['ejemplares.php', ['libro_id' => '1'], null];
        yield 'copies_of_reserved_book' => ['ejemplares.php', ['libro_id' => '6'], null];
        yield 'lend_form' => ['prestar.php', [], null];
        yield 'return_form' => ['devolver.php', [], null];
        yield 'loans_active' => ['prestamos.php', [], null];
        yield 'loans_overdue' => ['prestamos.php', ['filtro' => 'vencidos'], self::LATE];
        yield 'loans_returned' => ['prestamos.php', ['filtro' => 'devueltos'], null];
        yield 'reservations' => ['reservas.php', [], null];
        yield 'reservation_queue' => ['reservas.php', ['libro_id' => '4'], null];
        yield 'fines' => ['multas.php', [], null];
        yield 'search_empty' => ['buscar.php', [], null];
        yield 'search_results' => ['buscar.php', ['q' => 'Cervantes'], null];
        yield 'search_no_results' => ['buscar.php', ['q' => 'zzz'], null];
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('screens')]
    public function test_screen_matches_snapshot(string $path, array $query, ?string $date): void
    {
        if ($date !== null) {
            $this->app->setDate($date);
        }

        $name = $this->dataName();
        $this->assertSnapshotOf((string) $name, $this->app->get($path, $query));
    }

    public function test_login_screen_without_session_matches_snapshot(): void
    {
        $this->assertSnapshotOf('login', $this->anonymous()->get('login.php'));
    }

    public function test_login_screen_after_wrong_password_matches_snapshot(): void
    {
        $client = $this->anonymous();
        $response = $client->login('admin', 'not-the-password', true);

        $this->assertSnapshotOf('login_wrong_password', $response);
    }

    public function test_screen_is_stable_between_two_requests(): void
    {
        $first = $this->app->get('prestamos.php');
        $second = $this->app->get('prestamos.php');

        self::assertSame((string) $first->getBody(), (string) $second->getBody());
    }
}
