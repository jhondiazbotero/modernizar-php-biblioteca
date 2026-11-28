<?php

declare(strict_types=1);

use Library\Bootstrap\Application;
use Library\Config\MissingConfigurationException;
use Library\Http\ErrorPage;
use Library\Http\LegacyRouter;
use Library\Http\Request;
use Library\Http\Router;

$frontRoot = dirname(__DIR__);
require $frontRoot . '/vendor/autoload.php';

$frontRequest = Request::fromGlobals();
if (Router::handles($frontRequest->getPath())) {
    Application::handle($frontRequest)->send();

    return;
}

$frontFile = (new LegacyRouter($frontRoot . '/legacy'))->resolve($frontRequest->getPath());

if ($frontFile === null) {
    http_response_code(404);
    echo ErrorPage::notFound();
    return;
}

// Same working directory the screens had when Apache ran them directly.
chdir(dirname($frontFile));

// The screen runs in this scope: do not leave it variables that are not its own.
// $frontFile stays because the screen is included from it.
unset($frontRoot, $frontRequest);

try {
    require $frontFile;
} catch (MissingConfigurationException | \PDOException $e) {
    // The message can carry the variable name, host, user or SQL: it goes to the log, never to the page.
    error_log($e->getMessage());
    http_response_code(500);
    echo ErrorPage::serverError();
}
