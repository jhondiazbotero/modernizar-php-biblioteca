<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\Group;

final class SearchTest extends CharacterizationTestCase
{
    public function test_search_by_title(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => 'Quijote']));

        self::assertSame('1 resultado(s).', $html->text('//h2/following::p[last()]'));
        self::assertSame([['Don Quijote de la Mancha', 'Miguel de Cervantes', '1605', '2', 'Ejemplares']], $html->tableRows());
        self::assertSame('Quijote', $html->texts('//input[@id="q"]/@value')[0] ?? null);
    }

    public function test_search_by_author_ignores_case_and_accents(): void
    {
        $byAuthor = $this->page($this->app->get('buscar.php', ['q' => 'cervantes']));
        self::assertSame(['Don Quijote de la Mancha', 'Novelas ejemplares'], array_column($byAuthor->tableRows(), 0));

        $accented = $this->page($this->app->get('buscar.php', ['q' => 'galdos']));
        self::assertSame(['Fortunata y Jacinta', 'Marianela', 'Misericordia'], array_column($accented->tableRows(), 0));
    }

    public function test_search_without_matches_says_so(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => 'zzz']));

        self::assertSame('No se encontraron libros para «zzz».', $html->text('//h2/following::p[last()]'));
        self::assertSame(0, $html->count('//table'));
    }

    public function test_search_without_text_shows_only_the_form(): void
    {
        $html = $this->page($this->app->get('buscar.php'));

        self::assertSame(0, $html->count('//table'));
        self::assertNull($html->message());
    }

    public function test_search_counts_available_copies_of_each_book(): void
    {
        $rows = $this->page($this->app->get('buscar.php', ['q' => 'Misericordia']))->tableRows();

        self::assertSame([['Misericordia', 'Benito Pérez Galdós', '1897', '0', 'Ejemplares']], $rows);
    }

    /**
     * Current behavior (known defect F3): the search text is concatenated into the SQL, so a crafted text returns the whole catalog (the ORDER BY is commented out too, so the order is by id).
     */
    #[Group('known-defect')]
    public function test_f3_sql_injection_returns_the_whole_catalog(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => "' OR 1=1 -- "]));

        $titles = array_column($html->tableRows(), 0);
        $all = $this->db->fetchAll('SELECT titulo FROM libros ORDER BY id');
        self::assertCount(15, $titles);
        self::assertSame(array_column($all, 'titulo'), $titles);
        self::assertSame('15 resultado(s).', $html->text('//h2/following::p[last()]'));
    }

    /**
     * Current behavior (known defect F3): a single quote breaks the SQL and the MySQL error is printed on screen.
     */
    #[Group('known-defect')]
    public function test_f3_single_quote_shows_the_mysql_error(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => "'"]));

        self::assertSame('error', $html->messageKind());
        self::assertStringContainsString('You have an error in your SQL syntax', (string) $html->message());
        self::assertSame(0, $html->count('//table'));
    }
}
