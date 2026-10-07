<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;

require __DIR__ . '/../vendor/autoload.php';

$files = new Filesystem();

foreach ([
    'bootstrap/cache',
    'database',
    'storage/framework/cache/data',
    'storage/framework/sessions',
    'storage/framework/views',
    'storage/logs',
] as $directory) {
    $files->ensureDirectoryExists(__DIR__ . '/' . $directory);
}

if (!$files->exists(__DIR__ . '/.env')) {
    $environment = $files->get(__DIR__ . '/.env.example');
    $environment = str_replace('APP_KEY=', 'APP_KEY=base64:' . base64_encode(random_bytes(32)), $environment);
    $files->put(__DIR__ . '/.env', $environment);
}

if (!$files->exists(__DIR__ . '/database/database.sqlite')) {
    $files->put(__DIR__ . '/database/database.sqlite', '');
}
