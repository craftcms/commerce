<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Sites;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Promotion\Data\Sale;
use CraftCms\Commerce\Promotion\Sales;
use CraftCms\Commerce\Tests\Support\MockPurchasable;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

/**
 * Builds a dedicated promotable variant at a given price, for tests that apply a sale and
 * need to control the base price the sale's percentage is calculated against.
 */
function promotableVariant(OrdersFixture $fixture, string $sku, float $basePrice): Variant
{
    $variant = new Variant();
    $variant->title = $sku;
    $variant->setPrimaryOwner($fixture->product);
    $variant->setSku($sku);
    $variant->setBasePrice($basePrice);
    $variant->promotable = true;
    $variant->siteId = Sites::getCurrentSite()->id;
    if (!Elements::saveElement($variant)) {
        throw new RuntimeException('Could not save variant: ' . json_encode($variant->errors()->all()));
    }

    return Variant::find()->id($variant->id)->one();
}

test('price and promotional price round to currency precision, and subtotal reflects the promotional price', function() {
    $lineItem = new LineItem();
    $lineItem->setPrice(1.239);
    $lineItem->setPromotionalPrice(1.114);
    $lineItem->qty = 2;

    expect($lineItem->getPrice())->toBe(1.24);
    expect($lineItem->getPromotionalPrice())->toBe(1.11);
    expect($lineItem->getSalePrice())->toBe(1.11);
    expect($lineItem->getSubtotal())->toBe(2.22);
});

test('populate() sets price, sale price, and sku from a purchasable', function() {
    $purchasable = new MockPurchasable();
    $lineItem = new LineItem();
    $lineItem->populate($purchasable);

    expect($lineItem->getPrice())->toBe(25.10);
    expect($lineItem->getSalePrice())->toBe(25.10);
    expect($lineItem->getPromotionalAmount())->toBe(0.0);
    expect($lineItem->getSku())->toBe('commerce_testing_unique_sku');
    expect($lineItem->getOnPromotion())->toBeFalse();
});

test('getIsPromotable ignores a manual override when the line item has a live purchasable', function() {
    $fixture = OrdersFixture::seed();

    $lineItem = new LineItem();
    $lineItem->populate($fixture->blue);

    // Manually set the property to make sure it doesn't do anything when it's a purchasable line item.
    $lineItem->setIsPromotable(false);

    expect($lineItem->getIsPromotable())->toBeTrue();
});

test('getHasFreeShipping ignores a manual override when the line item has a live purchasable', function() {
    $fixture = OrdersFixture::seed();

    $lineItem = new LineItem();
    $lineItem->populate($fixture->blue);

    // Manually set the property to make sure it doesn't do anything when it's a purchasable line item.
    $lineItem->setHasFreeShipping(true);

    expect($lineItem->getHasFreeShipping())->toBeFalse();
});

test('populate() applies an active percentage sale to the line item price', function() {
    $fixture = OrdersFixture::seed();
    $variant = promotableVariant($fixture, 'rad-hood', 123.99);

    $sale = new Sale();
    $sale->name = 'My Percentage Sale';
    $sale->description = 'My test percentage sale.';
    // ->apply defaults to Sale::APPLY_BY_PERCENT already.
    $sale->applyAmount = -0.10;
    $sale->allGroups = true;
    $sale->allPurchasables = false;
    $sale->allCategories = true;
    $sale->setPurchasableIds([$variant->id]);
    if (!app(Sales::class)->saveSale($sale)) {
        throw new RuntimeException('Could not save sale: ' . json_encode($sale->errors()->all()));
    }

    $lineItem = new LineItem();
    $lineItem->populate($variant);

    expect(round($lineItem->getPrice(), 2))->toBe(123.99);
    expect(round($lineItem->getSalePrice(), 2))->toBe(111.59);
    expect(round($lineItem->getPromotionalAmount(), 2))->toBe(12.40);
    expect($lineItem->getOnPromotion())->toBeTrue();
});

test('a custom line item contributes its price times quantity to the order total', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Custom;
    $lineItem->description = 'Custom';
    $lineItem->setSku('custom-sku');
    $lineItem->setPrice(10.00);
    $lineItem->qty = 2;
    $lineItem->setIsPromotable(false);
    $lineItem->setHasFreeShipping(true);

    $order = new Order();
    $order->number = app(Carts::class)->generateCartNumber();
    $order->setLineItems([$lineItem]);

    expect($order->getTotal())->toEqual(20.00);
});

test('a custom line item does not include purchasable in extraFields, and toArray() does not throw', function() {
    $lineItem = new LineItem();
    $lineItem->type = LineItemType::Custom;
    $lineItem->description = 'Custom';
    $lineItem->setSku('custom-sku');
    $lineItem->setPrice(10.00);
    $lineItem->qty = 2;

    $order = new Order();
    $order->number = app(Carts::class)->generateCartNumber();
    $order->setLineItems([$lineItem]);

    expect($lineItem->extraFields())->not->toContain('purchasable');

    $data = $lineItem->toArray([], ['*']);
    expect($data)->toBeArray();

    $data = $lineItem->toArray([], ['purchasable']);
    expect($data)->toBeArray();
    expect($data)->not->toHaveKey('purchasable');
});

test('a purchasable line item includes purchasable in both extraFields and toArray()', function() {
    $fixture = OrdersFixture::seed();

    $lineItem = new LineItem();
    $lineItem->populate($fixture->blue);
    $lineItem->qty = 1;

    $order = new Order();
    $order->number = app(Carts::class)->generateCartNumber();
    $order->setLineItems([$lineItem]);

    expect($lineItem->extraFields())->toContain('purchasable');

    $data = $lineItem->toArray([], ['purchasable']);
    expect($data)->toHaveKey('purchasable');
    expect($data['purchasable'])->not->toBeNull();
});
