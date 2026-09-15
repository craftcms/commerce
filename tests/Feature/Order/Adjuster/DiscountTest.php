<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Adjuster\Discount;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Promotion\Data\Discount as DiscountModel;
use CraftCms\Commerce\Promotion\Discounts;

/**
 * Builds a bare in-memory line item (no persisted purchasable) with a manually-set
 * promotable flag — {@see LineItem::getIsPromotable()} falls back to that flag whenever
 * the line item has no resolvable purchasable, which is all `Discount::adjust()`'s own
 * matching/spreading logic cares about here.
 */
function discountLineItem(float $price, int $qty, bool $isPromotable): LineItem
{
    $lineItem = new LineItem();
    $lineItem->qty = $qty;
    $lineItem->setPrice($price);
    $lineItem->setIsPromotable($isPromotable);

    return $lineItem;
}

/**
 * Swaps in a partial mock of the `Discounts` service so `adjust()` only ever sees the
 * given discount as "active" and "matching the order" — real matching against line items
 * (promotability, category/purchasable restrictions) still runs for real.
 */
function mockActiveDiscount(DiscountModel $discount): void
{
    $mock = Mockery::mock(Discounts::class)->makePartial();
    $mock->shouldReceive('getAllActiveDiscounts')->andReturn([$discount]);
    $mock->shouldReceive('matchOrder')->andReturn(true);
    app()->instance(Discounts::class, $mock);
}

function orderLevelDiscount(float $baseDiscount): DiscountModel
{
    $discount = new DiscountModel();
    $discount->name = 'Order Level';
    $discount->description = 'Order level discount';
    $discount->allPurchasables = true;
    $discount->allCategories = true;
    $discount->stopProcessing = false;
    $discount->baseDiscount = $baseDiscount;

    return $discount;
}

test('a base order-level discount applies to a promotable line item', function() {
    mockActiveDiscount($discount = orderLevelDiscount(-10));

    $order = new Order();
    $order->setLineItems([discountLineItem(price: 100, qty: 1, isPromotable: true)]);

    $adjustments = app(Discount::class)->adjust($order);
    $order->setAdjustments($adjustments);

    expect($adjustments)->toHaveCount(1);
    $adjustment = collect($adjustments)->firstWhere('description', $discount->description);
    expect($adjustment)->not->toBeNull();
    expect($adjustment->amount)->toEqual(-10.0);
    expect($adjustment->type)->toBe('discount');

    expect($order->getTotalPrice())->toEqual(90.0);
    expect($order->getTotalDiscount())->toEqual(-10.0);
});

test('a base order-level discount does not apply to a non-promotable line item', function() {
    mockActiveDiscount(orderLevelDiscount(-10));

    $order = new Order();
    $order->setLineItems([discountLineItem(price: 100, qty: 1, isPromotable: false)]);

    $adjustments = app(Discount::class)->adjust($order);
    $order->setAdjustments($adjustments);

    expect($adjustments)->toHaveCount(0);
    expect($order->getTotalPrice())->toEqual(100.0);
    expect($order->getTotalDiscount())->toEqual(0.0);
});

test('a base order-level discount larger than one promotable line item spreads to it but skips the non-promotable one', function() {
    mockActiveDiscount($discount = orderLevelDiscount(-110));

    $order = new Order();
    $order->setLineItems([
        discountLineItem(price: 100, qty: 1, isPromotable: false),
        discountLineItem(price: 100, qty: 1, isPromotable: true),
    ]);

    $adjustments = app(Discount::class)->adjust($order);
    $order->setAdjustments($adjustments);

    expect($adjustments)->toHaveCount(1);
    $adjustment = collect($adjustments)->firstWhere('description', $discount->description);
    expect($adjustment)->not->toBeNull();
    // The discount is capped at the price of the only promotable line item, not the full base discount.
    expect($adjustment->amount)->toEqual(-100.0);

    expect($order->getTotalPrice())->toEqual(100.0);
    expect($order->getTotalDiscount())->toEqual(-100.0);
});
