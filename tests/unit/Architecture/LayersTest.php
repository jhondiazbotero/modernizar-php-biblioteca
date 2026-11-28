<?php

declare(strict_types=1);

namespace Library\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/** The layers only depend inwards: Domain on nothing, Application on Domain. */
final class LayersTest extends TestCase
{
    private const DOMAIN_RULES = [
        'PDO' => '/\bPDO\w*/',
        'Library\Infrastructure' => '/Library\\\\+Infrastructure/',
        'Library\Application' => '/Library\\\\+Application/',
        'Library\Http' => '/Library\\\\+Http/',
        'a superglobal' => '/\$(_GET|_POST|_SERVER|_SESSION|_COOKIE|_REQUEST|_ENV|_FILES|GLOBALS)\b/',
        'a legacy function' => '/(?<![\w>:$])(hoy|ahora|redirigir)\s*\(/',
    ];

    private const APPLICATION_RULES = [
        'PDO' => '/\bPDO\w*/',
        'Library\Infrastructure' => '/Library\\\\+Infrastructure/',
        'Library\Http' => '/Library\\\\+Http/',
    ];

    /** The HTTP layer talks to the application, never to the database or the infrastructure. */
    private const HTTP_RULES = [
        'PDO' => '/\bPDO\w*/',
        'Library\Infrastructure' => '/Library\\\\+Infrastructure/',
        'Library\Bootstrap' => '/Library\\\\+Bootstrap/',
    ];

    public function test_the_domain_depends_on_nothing_outside_itself(): void
    {
        $files = $this->phpFiles('Domain');

        self::assertGreaterThan(10, count($files), 'The domain files must be found.');
        self::assertSame([], $this->violations($files, self::DOMAIN_RULES));
    }

    public function test_the_application_does_not_know_pdo_or_infrastructure(): void
    {
        $files = $this->phpFiles('Application');

        self::assertGreaterThan(10, count($files), 'The application files must be found.');
        self::assertSame([], $this->violations($files, self::APPLICATION_RULES));
    }

    public function test_the_http_layer_does_not_know_pdo_or_infrastructure(): void
    {
        $files = $this->phpFiles('Http');

        self::assertGreaterThan(5, count($files), 'The HTTP files must be found.');
        self::assertSame([], $this->violations($files, self::HTTP_RULES));
    }

    public function test_the_checker_detects_what_it_forbids(): void
    {
        $bad = [
            'new \PDO($dsn)' => 'PDO',
            'use Library\Infrastructure\Clock\RequestClock;' => 'Library\Infrastructure',
            'use Library\Application\LendCopy;' => 'Library\Application',
            'use Library\Http\ErrorPage;' => 'Library\Http',
            '$x = $_POST["a"];' => 'a superglobal',
            '$x = $_SERVER["a"];' => 'a superglobal',
            '$d = hoy();' => 'a legacy function',
            '$d = ahora ();' => 'a legacy function',
            'redirigir("a", "b");' => 'a legacy function',
        ];
        foreach ($bad as $code => $rule) {
            self::assertSame([$rule], $this->rulesBroken("<?php\n" . $code . "\n", self::DOMAIN_RULES), $code);
        }

        self::assertSame(['PDO'], $this->rulesBroken("<?php\nnew \\PDO(\$dsn);\n", self::HTTP_RULES));
        self::assertSame(['Library\\Infrastructure'], $this->rulesBroken("<?php\nuse Library\\Infrastructure\\Clock\\RequestClock;\n", self::HTTP_RULES));
        self::assertSame(['Library\\Http'], $this->rulesBroken("<?php\nuse Library\\Http\\Request;\n", self::APPLICATION_RULES));

        // comments and method names that merely look alike are fine
        self::assertSame([], $this->rulesBroken("<?php\n// uses PDO and hoy()\n/** \$_POST */\n\$clock->hoy();\n\$this->ahora();\n", self::DOMAIN_RULES));
    }

    /** @return string[] */
    private function phpFiles(string $layer): array
    {
        $directory = dirname(__DIR__, 3) . '/src/' . $layer;
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && substr($file->getFilename(), -4) === '.php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @param string[]              $files
     * @param array<string, string> $rules
     *
     * @return string[] "file: rule" for every rule a file breaks
     */
    private function violations(array $files, array $rules): array
    {
        $found = [];
        foreach ($files as $file) {
            foreach ($this->rulesBroken((string) file_get_contents($file), $rules) as $rule) {
                $found[] = basename($file) . ': ' . $rule;
            }
        }

        return $found;
    }

    /**
     * @param array<string, string> $rules
     *
     * @return string[]
     */
    private function rulesBroken(string $source, array $rules): array
    {
        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token)) {
                if ($token[0] !== T_COMMENT && $token[0] !== T_DOC_COMMENT) {
                    $code .= $token[1];
                }
            } else {
                $code .= $token;
            }
        }

        $broken = [];
        foreach ($rules as $name => $pattern) {
            if (preg_match($pattern, $code) === 1) {
                $broken[] = $name;
            }
        }

        return $broken;
    }
}
