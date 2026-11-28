<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;

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

    public function test_text_that_looks_like_sql_is_searched_as_plain_text(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => "' OR 1=1 -- "]));

        self::assertSame(0, $html->count('//table'));
        self::assertSame("No se encontraron libros para «' OR 1=1 -- ».", $html->text('//h2/following::p[last()]'));
        self::assertNull($html->message());
    }

    public function test_a_single_quote_shows_the_normal_empty_result_without_a_database_error(): void
    {
        $response = $this->app->get('buscar.php', ['q' => "'"]);
        $html = $this->page($response);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame("No se encontraron libros para «'».", $html->text('//h2/following::p[last()]'));
        self::assertNull($html->message());
        self::assertSame(0, $html->count('//table'));
        self::assertStringNotContainsString('SQL', (string) $response->getBody());
    }

    public function test_a_percent_sign_is_searched_as_text_and_does_not_return_everything(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => '%']));

        self::assertSame(0, $html->count('//table'));
        self::assertSame('No se encontraron libros para «%».', $html->text('//h2/following::p[last()]'));
    }

    public function test_underscore_and_backslash_are_searched_as_text(): void
    {
        foreach (['_', '\\', 'Do_'] as $text) {
            $html = $this->page($this->app->get('buscar.php', ['q' => $text]));

            self::assertSame(0, $html->count('//table'), "«{$text}» must not match anything in the seed");
        }
    }

    public function test_wildcard_characters_only_match_a_book_that_really_contains_them(): void
    {
        $this->db->execute('INSERT INTO libros (titulo, autor) VALUES (?, ?)', ['100% Cierto', 'Ana_Perez']);
        $this->db->execute('INSERT INTO libros (titulo, autor) VALUES (?, ?)', ['100 Cierto', 'AnaXPerez']);
        $this->db->execute('INSERT INTO libros (titulo, autor) VALUES (?, ?)', ['Ruta\\Casa', 'Nadie']);

        $percent = array_column($this->page($this->app->get('buscar.php', ['q' => '0% C']))->tableRows(), 0);
        $underscore = array_column($this->page($this->app->get('buscar.php', ['q' => 'a_P']))->tableRows(), 0);
        $backslash = array_column($this->page($this->app->get('buscar.php', ['q' => 'a\\C']))->tableRows(), 0);

        self::assertSame(['100% Cierto'], $percent);
        self::assertSame(['100% Cierto'], $underscore);
        self::assertSame(['Ruta\\Casa'], $backslash);
    }

    public function test_results_are_always_ordered_by_title(): void
    {
        $html = $this->page($this->app->get('buscar.php', ['q' => 'a']));

        $titles = array_column($html->tableRows(), 0);
        $expected = array_column(
            $this->db->fetchAll("SELECT titulo FROM libros WHERE titulo LIKE '%a%' OR autor LIKE '%a%' ORDER BY titulo ASC"),
            'titulo'
        );
        $byId = array_column(
            $this->db->fetchAll("SELECT titulo FROM libros WHERE titulo LIKE '%a%' OR autor LIKE '%a%' ORDER BY id ASC"),
            'titulo'
        );
        self::assertGreaterThan(5, count($titles));
        self::assertNotSame($byId, $expected, 'The check needs a search where title order differs from id order');
        self::assertSame($expected, $titles);
    }
}
