<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/unit',
    ])
    ->withSkip([
        Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector::class => [
            __DIR__ . '/src/console/controllers/GatewaysController.php',
        ],
        Rector\Php80\Rector\Class_\StringableForToStringRector::class,
    ])
    ->withPhpSets(php80: true);
