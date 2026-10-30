<?php

declare(strict_types=1);

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
define('TESTBENCH_WORKING_PATH', dirname(__DIR__, levels: 2));

require TESTBENCH_WORKING_PATH . '/vendor/autoload.php';

chdir(TESTBENCH_WORKING_PATH);

$app = require TESTBENCH_WORKING_PATH . '/vendor/orchestra/testbench-core/laravel/bootstrap/app.php';

$app->handleRequest(Request::capture());
