<?php

declare(strict_types=1);

use CraftCms\Commerce\Helpers\Localization;

test('normalizePercentage', function (mixed $number, ?float $expected) {
    expect(Localization::normalizePercentage($number))->toBe($expected);
})->with([
    'null' => [null, 0.0],
    'empty string' => ['', 0.0],
    'percent symbol alone' => ['%', 0.0],
    'padded percent symbol' => [' % ', 0.0],
    'int zero' => [0, 0.0],
    'float' => [0.5, 0.5],
    'int' => [50, 50.0],
    'one' => [1, 1.0],
    'string zero' => ['0', 0.0],
    'string one' => ['1', 0.01],
    'string fifty' => ['50', 0.5],
    'padded string zero' => [' 0.0 ', 0.0],
    'fraction with trailing percent' => [' .5 % ', 0.005],
    'fraction with leading percent' => [' % 0.5 ', 0.005],
]);
