<?php

declare(strict_types=1);

namespace Library\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/** The templates only print: every value goes through e(), and they know nothing about the database or the request. */
final class TemplatesTest extends TestCase
{
    /**
     * Expressions a template may print without e(), by template. Each one is HTML that is
     * already safe; anything new here needs a reason next to it.
     */
    private const ALREADY_ESCAPED = [
        // The body of the page: it was rendered from another template, whose values went through e().
        'layout.php' => ['$content'],
    ];

    public function test_every_printed_variable_goes_through_the_escape_helper(): void
    {
        $files = $this->templates();
        self::assertGreaterThan(0, count($files), 'The templates must be found.');

        $found = [];
        foreach ($files as $name => $file) {
            foreach ($this->unescapedOutputs((string) file_get_contents($file), self::ALREADY_ESCAPED[$name] ?? []) as $expression) {
                $found[] = $name . ': ' . $expression;
            }
        }

        self::assertSame([], $found);
    }

    public function test_the_templates_do_not_touch_the_database_or_the_request(): void
    {
        $found = [];
        foreach ($this->templates() as $name => $file) {
            $source = (string) file_get_contents($file);
            if (preg_match('/\bPDO\w*/', $source) === 1) {
                $found[] = $name . ': PDO';
            }
            if (preg_match('/\$(_[A-Z]+|GLOBALS)\b/', $source) === 1) {
                $found[] = $name . ': a superglobal';
            }
        }

        self::assertSame([], $found);
    }

    public function test_the_whitelist_only_names_templates_that_exist(): void
    {
        self::assertSame([], array_values(array_diff(array_keys(self::ALREADY_ESCAPED), array_keys($this->templates()))));
    }

    /** @return array<string, array{string, array<int, string>}> */
    public function printedCode(): array
    {
        return [
            'short tag with a bare variable' => ['<p><?= $name ?></p>', ['$name']],
            'echo with a bare variable' => ['<?php echo $name; ?>', ['$name']],
            'echo of a bare array item' => ['<?php echo $row[\'name\']; ?>', ['$row[\'name\']']],
            'escaped value followed by a bare one' => ['<?= e($a) . $b ?>', ['e($a).$b']],
            'a function that is not e()' => ['<?= htmlspecialchars($a) ?>', ['htmlspecialchars($a)']],
            'a formatter without e()' => ['<?= Format::date($a) ?>', ['Format::date($a)']],
            'echo of a concatenation' => ['<?php echo \'<b>\' . $a . \'</b>\'; ?>', ['\'<b>\'.$a.\'</b>\'']],
        ];
    }

    /**
     * @dataProvider printedCode
     *
     * @param array<int, string> $expected
     */
    public function test_the_checker_flags_what_is_not_escaped(string $code, array $expected): void
    {
        self::assertSame($expected, $this->unescapedOutputs($code, []));
    }

    public function test_the_checker_accepts_escaped_and_literal_output(): void
    {
        $ok = '<?= e($a) ?> <?= e(Format::date($a)) ?> <?= e($row[\'name\']) ?> <?php echo \' class="inactivo"\'; ?> <?= 12 ?> <?php if ($x) { ?>text<?php } ?>';

        self::assertSame([], $this->unescapedOutputs($ok, []));
    }

    public function test_the_checker_accepts_a_whitelisted_expression_only_where_it_is_listed(): void
    {
        self::assertSame([], $this->unescapedOutputs('<?= $content ?>', ['$content']));
        self::assertSame(['$other'], $this->unescapedOutputs('<?= $other ?>', ['$content']));
    }

    /** @return array<string, string> template name => absolute path */
    private function templates(): array
    {
        $directory = dirname(__DIR__, 3) . '/templates';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[substr($file->getPathname(), strlen($directory) + 1)] = $file->getPathname();
            }
        }
        ksort($files);

        return $files;
    }

    /**
     * Expressions printed by "<?=" or "echo" that use a variable and are not exactly one call to e().
     *
     * @param array<int, string> $allowed expressions (without spaces) that may be printed as they are
     *
     * @return array<int, string>
     */
    private function unescapedOutputs(string $source, array $allowed): array
    {
        $tokens = token_get_all($source);
        $found = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if (!is_array($token) || ($token[0] !== T_OPEN_TAG_WITH_ECHO && $token[0] !== T_ECHO)) {
                continue;
            }

            $expression = [];
            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];
                if ($next === ';' || (is_array($next) && $next[0] === T_CLOSE_TAG)) {
                    break;
                }
                if (is_array($next) && ($next[0] === T_WHITESPACE || $next[0] === T_COMMENT || $next[0] === T_DOC_COMMENT)) {
                    continue;
                }
                $expression[] = $next;
            }
            $i = $j;

            if (!$this->usesAVariable($expression)) {
                continue;
            }
            $text = $this->text($expression);
            if (in_array($text, $allowed, true) || $this->isOneCallToEscape($expression)) {
                continue;
            }
            $found[] = $text;
        }

        return $found;
    }

    /** @param array<int, mixed> $tokens */
    private function usesAVariable(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (is_array($token) && $token[0] === T_VARIABLE) {
                return true;
            }
        }

        return false;
    }

    /** True for e( ... ) when the parenthesis that opens after "e" closes at the very end. */
    private function isOneCallToEscape(array $tokens): bool
    {
        if (count($tokens) < 4 || !is_array($tokens[0]) || $tokens[0][0] !== T_STRING || $tokens[0][1] !== 'e' || $tokens[1] !== '(') {
            return false;
        }
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token === '(') {
                $depth++;
            } elseif ($token === ')') {
                $depth--;
                if ($depth === 0) {
                    return $index === count($tokens) - 1;
                }
            }
        }

        return false;
    }

    /** @param array<int, mixed> $tokens */
    private function text(array $tokens): string
    {
        $text = '';
        foreach ($tokens as $token) {
            $text .= is_array($token) ? $token[1] : $token;
        }

        return $text;
    }
}
