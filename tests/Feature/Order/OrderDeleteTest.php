<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Commerce\Order\Carts;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

function createLineItemForDeleteTest(Order $order, OrdersFixture $fixture, LineItemType $type): LineItem
{
    if ($type === LineItemType::Custom) {
        return app(LineItems::class)->create($order, [
            'sku' => 'custom-sku',
            'description' => 'Custom',
            'price' => 10,
            'qty' => 1,
            'taxCategoryId' => app(TaxCategories::class)->getDefaultTaxCategory()->id,
            'shippingCategoryId' => app(ShippingCategories::class)->getDefaultShippingCategory($order->storeId)->id,
        ], LineItemType::Custom);
    }

    return app(LineItems::class)->create($order, [
        'purchasableId' => $fixture->blue->id,
        'qty' => 1,
    ]);
}

test('a completed order can be deleted', function(array $lineItemTypes) {
    $fixture = OrdersFixture::seed();

    $order = new Order();
    $order->number = app(Carts::class)->generateCartNumber();
    $order->setLineItems(array_map(fn(LineItemType $type) => createLineItemForDeleteTest($order, $fixture, $type), $lineItemTypes));

    expect($order->markAsComplete())->toBeTrue()
        ->and(Elements::deleteElement($order))->toBeTrue();
})->with([
    'purchasable line items only' => [[LineItemType::Purchasable]],
    'custom line items only' => [[LineItemType::Custom]],
    'mixture of purchasable and custom line items' => [[LineItemType::Purchasable, LineItemType::Custom]],
]);
