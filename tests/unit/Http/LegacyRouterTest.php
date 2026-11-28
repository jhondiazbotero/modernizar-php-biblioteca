<?php

declare(strict_types=1);

namespace Library\Tests\Http;

use Library\Http\LegacyRouter;
use PHPUnit\Framework\TestCase;

final class LegacyRouterTest extends TestCase
{
    private const LEGACY_DIR = '/var/www/app/legacy';

    private const SCREENS = [
        'buscar.php',
    ];

    /** @return array<string, array{string}> */
    public function screens(): array
    {
        $cases = [];
        foreach (self::SCREENS as $screen) {
            $cases['/' . $screen] = [$screen];
        }

        return $cases;
    }

    /**
     * @dataProvider screens
     */
    public function test_each_screen_resolves_to_its_legacy_file(string $screen): void
    {
        $router = new LegacyRouter(self::LEGACY_DIR);

        self::assertSame(self::LEGACY_DIR . '/' . $screen, $router->resolve('/' . $screen));
    }

    public function test_a_trailing_slash_in_the_legacy_directory_is_ignored(): void
    {
        $router = new LegacyRouter(self::LEGACY_DIR . '/');

        self::assertSame(self::LEGACY_DIR . '/buscar.php', $router->resolve('/buscar.php'));
    }

    /** @return array<string, array{?string}> */
    public function paths_that_must_not_resolve(): array
    {
        return [
            'unknown screen' => ['/no-existe.php'],
            'screen that already has a controller' => ['/index.php'],
            'login that already has a controller' => ['/login.php'],
            'logout that already has a controller' => ['/logout.php'],
            'root that already has a controller' => ['/'],
            'parent directory' => ['/../legacy/socios.php'],
            'parent directory inside the path' => ['/css/../socios.php'],
            'encoded parent directory' => ['/%2e%2e/legacy/socios.php'],
            'include file' => ['/includes/conexion.php'],
            'legacy prefix' => ['/legacy/socios.php'],
            'extra path after the screen' => ['/socios.php/extra'],
            'trailing slash on a screen' => ['/socios.php/'],
            'double slash' => ['//socios.php'],
            'no leading slash' => ['socios.php'],
            'different case' => ['/SOCIOS.PHP'],
            'encoded dot' => ['/socios%2ephp'],
            'null byte' => ["/socios.php\0.txt"],
            'backslash' => ['/..\\legacy\\socios.php'],
            'stylesheet' => ['/css/estilo.css'],
            'empty path' => [''],
            'missing path' => [null],
        ];
    }

    /**
     * @dataProvider paths_that_must_not_resolve
     */
    public function test_paths_outside_the_whitelist_do_not_resolve(?string $path): void
    {
        $router = new LegacyRouter(self::LEGACY_DIR);

        self::assertNull($router->resolve($path));
    }
}
