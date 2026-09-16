<?php

declare(strict_types=1);

use CraftCms\Commerce\Promotion\Data\Sale;

test('setCategoryIds dedupes and getCategoryIds returns blank array when unset', function() {
    $sale = new Sale();

    expect($sale->getCategoryIds())->toBe([]);

    $sale->setCategoryIds([1, 2, 3, 4, 1]);

    expect($sale->getCategoryIds())->toBe([1, 2, 3, 4]);
});

test('setPurchasableIds dedupes and getPurchasableIds returns blank array when unset', function() {
    $sale = new Sale();

    expect($sale->getPurchasableIds())->toBe([]);

    $sale->setPurchasableIds([1, 2, 3, 4, 1]);

    expect($sale->getPurchasableIds())->toBe([1, 2, 3, 4]);
});

test('setUserGroupIds dedupes and getUserGroupIds returns blank array when unset', function() {
    $sale = new Sale();

    expect($sale->getUserGroupIds())->toBe([]);

    $sale->setUserGroupIds([1, 2, 3, 4, 1]);

    expect($sale->getUserGroupIds())->toBe([1, 2, 3, 4]);
});

test('getApplyAmountAsPercent formats the (always negative) apply amount as a percent', function(string|int|float $applyAmount, string $expected) {
    $sale = new Sale();
    $sale->applyAmount = (float)$applyAmount;

    expect($sale->getApplyAmountAsPercent())->toBe($expected);
})->with([
    ['-0.1000', '10%'],
    [0, '0%'],
    [-0.1, '10%'],
    [-0.15, '15%'],
    [-0.105, '10.5%'],
    [-0.10504, '10.504%'],
    ['-0.1050400', '10.504%'],
]);

test('getApplyAmountAsFlat flips the sign of the (always negative) apply amount', function() {
    $sale = new Sale();
    $sale->applyAmount = -0.15;

    expect($sale->getApplyAmountAsFlat())->toBe('0.15');
});
