<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// One template database per run; each test copies it over the application tables.
\Library\Characterization\Database::createTemplate();
