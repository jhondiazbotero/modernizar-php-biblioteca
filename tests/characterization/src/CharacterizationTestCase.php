<?php

declare(strict_types=1);

namespace Library\Characterization;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class: pristine database, a logged-in client and a few shared helpers.
 */
abstract class CharacterizationTestCase extends TestCase
{
    use Snapshot;

    protected Database $db;
    protected AppClient $app;

    protected function setUp(): void
    {
        $this->db = Database::instance();
        $this->db->reset();
        $this->app = new AppClient();
        $this->app->login();
    }

    /** A client without a session. */
    protected function anonymous(): AppClient
    {
        return new AppClient();
    }

    protected function page(ResponseInterface $response): Html
    {
        return Html::fromResponse($response);
    }

    protected function assertSnapshotOf(string $name, ResponseInterface $response): void
    {
        self::assertSame(200, $response->getStatusCode(), "Page for snapshot {$name} did not answer 200");
        $this->assertMatchesSnapshot($name, Html::normalize((string) $response->getBody()));
    }

    protected function assertRedirectsTo(string $location, ResponseInterface $response): void
    {
        self::assertSame(302, $response->getStatusCode());
        self::assertSame($location, $response->getHeaderLine('Location'));
    }

    /** The banner after a followed redirect says exactly this, with this kind (ok or error). */
    protected function assertMessage(string $kind, string $text, ResponseInterface $response): void
    {
        $html = $this->page($response);
        self::assertSame($text, $html->message());
        self::assertSame($kind, $html->messageKind());
    }

    protected function rows(string $table): int
    {
        return (int) $this->db->fetchValue("SELECT COUNT(*) FROM {$table}");
    }

    /**
     * Full contents of every table, to prove that a rejected action changed nothing.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    protected function dbState(): array
    {
        $state = [];
        foreach (['bibliotecarios', 'socios', 'libros', 'ejemplares', 'prestamos', 'reservas'] as $table) {
            $state[$table] = $this->db->fetchAll("SELECT * FROM {$table} ORDER BY id");
        }

        return $state;
    }
}
