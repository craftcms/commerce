<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

test('getAllLineItemsByOrderId returns an empty array for an unknown order, and all line items for a real one', function() {
    expect(app(LineItems::class)->getAllLineItemsByOrderId(9999))->toBe([]);

    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $lineItems = app(LineItems::class)->getAllLineItemsByOrderId($order->id);

    expect($lineItems)->toBeArray();
    expect($lineItems)->toHaveCount(2);
});

test('resolveLineItem is consistent across repeated calls for an unsaved order', function() {
    $fixture = OrdersFixture::seed();
    $order = new Order();

    $first = app(LineItems::class)->resolveLineItem($order, $fixture->blue->id, ['giftWrapped' => 'no']);
    $second = app(LineItems::class)->resolveLineItem($order, $fixture->blue->id, ['giftWrapped' => 'no']);

    expect($second)->toBeInstanceOf(LineItem::class);
    expect($second->getPrice())->toBe($first->getPrice());
    expect($second->getSalePrice())->toBe($first->getSalePrice());
    expect($second->getOptionsSignature())->toBe($first->getOptionsSignature());
    expect($second->purchasableId)->toBe($first->purchasableId);
    expect($second->orderId)->toBe($first->orderId);
});

test('resolveLineItem always returns a brand-new line item for a completed order, even with matching purchasable and options', function() {
    // The options signature persisted for a completed order's line item is salted with the line
    // item's own id (see LineItem::getOptionsSignature()), so the plain options-only signature
    // resolveLineItem() looks up by can never match it — every resolve on a completed order
    // creates a fresh line item rather than reusing the existing one.
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $orderLineItem = $order->getLineItems()[0];

    $resolvedLineItem = app(LineItems::class)->resolveLineItem($order, $orderLineItem->purchasableId, $orderLineItem->getOptions());

    expect($resolvedLineItem)->toBeInstanceOf(LineItem::class);
    expect($resolvedLineItem->getPrice())->toBe($orderLineItem->getPrice());
    expect($resolvedLineItem->getSalePrice())->toBe($orderLineItem->getSalePrice());
    expect($resolvedLineItem->getOptionsSignature())->not->toBe($orderLineItem->getOptionsSignature());
    expect($resolvedLineItem->purchasableId)->toBe($orderLineItem->purchasableId);
    expect($resolvedLineItem->orderId)->toBe($orderLineItem->orderId);
});

test('resolveLineItem populates price from the purchasable for a new line item', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $lineItem = $order->getLineItems()[1];

    $resolvedLineItem = app(LineItems::class)->resolveLineItem($order, $lineItem->purchasableId, $lineItem->getOptions());

    expect($resolvedLineItem)->toBeInstanceOf(LineItem::class);
    expect($resolvedLineItem->getPrice())->toBe($fixture->blue->getPrice());
});

test('resolveLineItem populates price from the purchasable for an unsaved order', function() {
    $fixture = OrdersFixture::seed();
    $order = new Order();

    $resolvedLineItem = app(LineItems::class)->resolveLineItem($order, $fixture->blue->id, ['giftWrapped' => 'no']);

    expect($resolvedLineItem)->toBeInstanceOf(LineItem::class);
    expect($resolvedLineItem->getPrice())->toBe($fixture->blue->getPrice());
});

test('getLineItemById returns the matching line item', function() {
    $fixture = OrdersFixture::seed();
    $lineItems = $fixture->orders['completed-new']->getLineItems();

    $lineItem = app(LineItems::class)->getLineItemById($lineItems[0]->id);

    expect($lineItem->purchasableId)->toBe($lineItems[0]->purchasableId);
    expect($lineItem->qty)->toBe($lineItems[0]->qty);
});

test('a line item snapshot is unpacked identically whether fetched by id or as part of the order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $lineItemById = app(LineItems::class)->getLineItemById($order->getLineItems()[0]->id);
    /** @var LineItem $lineItemFromAll */
    $lineItemFromAll = collect(app(LineItems::class)->getAllLineItemsByOrderId($order->id))->firstWhere('id', $lineItemById->id);

    expect($lineItemById->getSnapshot())->toBeArray();
    expect($lineItemFromAll->getSnapshot())->toBeArray();
    expect($lineItemFromAll->getSnapshot())->toBe($lineItemById->getSnapshot());
});

test('create() creates a line item on the order with the given params', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $sourceLineItem = $order->getLineItems()[0];
    $qty = 4;
    $note = 'My note';

    $lineItem = app(LineItems::class)->create($order, [
        'purchasableId' => $sourceLineItem->purchasableId,
        'options' => $sourceLineItem->getOptions(),
        'qty' => $qty,
        'note' => $note,
    ]);

    expect($lineItem)->toBeInstanceOf(LineItem::class);
    expect($lineItem->orderId)->toBe($order->id);
    expect($lineItem->purchasableId)->toBe($sourceLineItem->purchasableId);
    expect($lineItem->getOptions())->toBe($sourceLineItem->getOptions());
    expect($lineItem->qty)->toBe($qty);
    expect($lineItem->note)->toBe($note);
});
