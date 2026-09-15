<?php

declare(strict_types=1);

use CraftCms\Commerce\Tax\Data\TaxRate;

test('getRateAsPercent formats the rate as a percent', function(string|int|float $rate, string $expected) {
    $taxRate = new TaxRate();
    $taxRate->rate = (float)$rate;

    expect($taxRate->getRateAsPercent())->toBe($expected);
})->with([
    ['0.1000', '10%'],
    [0, '0%'],
    [0.1, '10%'],
    [0.15, '15%'],
    [0.105, '10.5%'],
    [0.10504, '10.504%'],
    ['0.1050400', '10.504%'],
]);
