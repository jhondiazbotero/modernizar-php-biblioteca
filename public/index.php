<?php

declare(strict_types=1);

use Library\Config\MissingConfigurationException;
use Library\Http\ErrorPage;
use Library\Http\LegacyRouter;

$frontRoot = dirname(__DIR__);
require $frontRoot . '/vendor/autoload.php';

$frontPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$frontFile = (new LegacyRouter($frontRoot . '/legacy'))->resolve(is_string($frontPath) ? $frontPath : null);

if ($frontFile === null) {
    http_response_code(404);
    echo ErrorPage::notFound();
    return;
}

// Same working directory the screens had when Apache ran them directly.
chdir(dirname($frontFile));

// The screen runs in this scope: do not leave it variables that are not its own.
// $frontFile stays because the screen is included from it.
unset($frontRoot, $frontPath);

try {
    require $frontFile;
} catch (MissingConfigurationException | \PDOException $e) {
    // The message can carry the variable name, host, user or SQL: it goes to the log, never to the page.
    error_log($e->getMessage());
    http_response_code(500);
    echo ErrorPage::serverError();
}
