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

try {
    require $frontFile;
} catch (MissingConfigurationException $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo ErrorPage::serverError();
}
