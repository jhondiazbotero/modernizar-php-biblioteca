<?php

declare(strict_types=1);

namespace Library\Http;

/**
 * Maps a requested URL path to one of the legacy screens.
 *
 * Only paths listed in SCREENS resolve. Nothing taken from the request is ever
 * used to build a file name, so "/../", "/includes/..." or "/legacy/..." can
 * not reach anything outside the list.
 */
final class LegacyRouter
{
    /** URL path => file inside the legacy directory. */
    private const SCREENS = [
        '/buscar.php' => 'buscar.php',
    ];

    /** @var string */
    private $legacyDir;

    public function __construct(string $legacyDir)
    {
        $this->legacyDir = rtrim($legacyDir, '/');
    }

    /** Absolute path of the legacy file for the requested path, or null when it is not a known screen. */
    public function resolve(?string $path): ?string
    {
        if ($path === null || !isset(self::SCREENS[$path])) {
            return null;
        }

        return $this->legacyDir . '/' . self::SCREENS[$path];
    }
}
