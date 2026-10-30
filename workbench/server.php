<?php

declare(strict_types=1);

$publicPath = __DIR__ . '/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if ($uri !== '/' && is_file($publicPath . $uri)) {
    return false;
}

require $publicPath . '/index.php';
