<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    private function view(): View
    {
        return new View(__DIR__ . '/fixtures');
    }

    public function test_it_renders_a_template_with_its_data(): void
    {
        self::assertSame("Hola Ana.\n", $this->view()->render('greeting.php', ['name' => 'Ana']));
    }

    public function test_the_template_prints_data_escaped(): void
    {
        self::assertSame("Hola &lt;b&gt;Ana&lt;/b&gt;.\n", $this->view()->render('greeting.php', ['name' => '<b>Ana</b>']));
    }

    public function test_a_trailing_slash_in_the_directory_is_ignored(): void
    {
        $view = new View(__DIR__ . '/fixtures/');

        self::assertSame("Hola Ana.\n", $view->render('greeting.php', ['name' => 'Ana']));
    }

    public function test_a_failing_template_leaves_no_output_buffer_open(): void
    {
        $level = ob_get_level();

        try {
            $this->view()->render('broken.php');
            self::fail('The exception of the template must reach the caller.');
        } catch (\RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertSame($level, ob_get_level());
    }

    /** @return array<string, array{string}> */
    public function invalidNames(): array
    {
        return [
            'parent directory' => ['../greeting.php'],
            'nested parent directory' => ['a/../greeting.php'],
            'absolute path' => ['/etc/passwd'],
            'not php' => ['greeting.txt'],
            'null byte' => ["greeting.php\0.txt"],
            'upper case' => ['Greeting.php'],
            'empty' => [''],
            'directory only' => ['sub/'],
        ];
    }

    /** @dataProvider invalidNames */
    public function test_names_that_are_not_templates_are_rejected(string $name): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->view()->render($name);
    }

    public function test_a_missing_template_is_reported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Template not found: missing.php');

        $this->view()->render('missing.php');
    }

    public function test_the_helper_escapes_every_character_that_matters_in_html(): void
    {
        self::assertSame('&lt;b&gt;Ana&lt;/b&gt;', e('<b>Ana</b>'));
        self::assertSame('Ana &amp; Luis', e('Ana & Luis'));
        self::assertSame('&quot;a&quot; &#039;b&#039;', e('"a" \'b\''));
    }

    public function test_the_helper_accepts_what_the_database_returns(): void
    {
        self::assertSame('', e(null));
        self::assertSame('12', e(12));
        self::assertSame('6500.00', e('6500.00'));
        self::assertSame('1', e(true));
        self::assertSame('', e(false));
    }

    public function test_the_helper_replaces_invalid_utf8_instead_of_dropping_the_value(): void
    {
        self::assertSame("a\u{FFFD}b", e("a\xC3b"));
    }

    public function test_the_helper_keeps_accents_and_the_opening_quotes(): void
    {
        self::assertSame('Cola de «Ñandú»', e('Cola de «Ñandú»'));
    }
}
