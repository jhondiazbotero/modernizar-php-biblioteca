<?php

declare(strict_types=1);

use Library\Bootstrap\Application;
use Library\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

Application::handle(Request::fromGlobals())->send();
