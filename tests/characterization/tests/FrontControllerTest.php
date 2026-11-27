<?php

declare(strict_types=1);

namespace Library\Characterization\Tests;

use Library\Characterization\CharacterizationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The single entry point only serves the known screens: anything else is a 404.
 */
final class FrontControllerTest extends CharacterizationTestCase
{
    /** @return array<string, array{string}> */
    public static function unknownPaths(): array
    {
        return [
            'unknown screen' => ['/no-existe.php'],
            'legacy directory is not public' => ['/legacy/socios.php'],
        ];
    }

    #[DataProvider('unknownPaths')]
    public function test_unknown_paths_answer_404(string $path): void
    {
        $response = $this->app->get($path);

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('Página no encontrada', (string) $response->getBody());
    }
}
