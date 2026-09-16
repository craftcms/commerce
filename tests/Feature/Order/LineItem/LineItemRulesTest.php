<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;

test('validate() passes for a valid custom line item', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Custom;
    $lineItem->qty = 1;
    $lineItem->taxCategoryId = 1;
    $lineItem->shippingCategoryId = 1;
    $lineItem->setPrice(10);

    expect($lineItem->validate())->toBeTrue();
});

test('validate() fails when qty is below 1', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Custom;
    $lineItem->qty = 0;
    $lineItem->taxCategoryId = 1;
    $lineItem->shippingCategoryId = 1;

    expect($lineItem->validate())->toBeFalse();
    expect($lineItem->errors()->has('qty'))->toBeTrue();
});

test('validate() fails when price is negative', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Custom;
    $lineItem->qty = 1;
    $lineItem->taxCategoryId = 1;
    $lineItem->shippingCategoryId = 1;
    $lineItem->setPrice(-5);

    expect($lineItem->validate())->toBeFalse();
    expect($lineItem->errors()->has('price'))->toBeTrue();
});

test('validate() requires a snapshot when type is Purchasable', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Purchasable;
    $lineItem->qty = 1;
    $lineItem->taxCategoryId = 1;
    $lineItem->shippingCategoryId = 1;

    expect($lineItem->validate())->toBeFalse();
    expect($lineItem->errors()->has('snapshot'))->toBeTrue();
});
