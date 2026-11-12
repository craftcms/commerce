<?php

declare(strict_types=1);

use CraftCms\Commerce\Order\Edit\Enums\OrderAddressType;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Validation\ValidationException;

/**
 * @param list<array<string, mixed>> $operations
 * @return list<string|null>
 */
function orderOperationSummaries(Order $order, array $operations): array
{
    $service = app(OrderOperations::class);
    $edit = $service->editFromArray(['version' => OrderEdit::VERSION, 'operations' => $operations]);

    return $service->summaries($order, $edit->operations);
}

it('summarises a change as what it was and what it becomes', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $order->reference = 'PO-1';

    expect(orderOperationSummaries($order, [
        ['type' => 'setReference', 'reference' => 'PO-2'],
        ['type' => 'setCouponCode', 'couponCode' => null],
    ]))->toBe(['PO-1 → PO-2', null === $order->couponCode ? 'None → None' : $order->couponCode . ' → None']);
});

it('summarises each operation against the order as earlier ones left it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $order->reference = 'PO-1';

    expect(orderOperationSummaries($order, [
        ['type' => 'setReference', 'reference' => 'PO-2'],
        ['type' => 'setReference', 'reference' => 'PO-3'],
    ]))->toBe(['PO-1 → PO-2', 'PO-2 → PO-3']);
});

it('summarises a line item change by its item and the values that changed', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];
    $description = $lineItem->getDescription();
    $qty = $lineItem->qty;
    $newQty = $qty + 2;

    $summary = orderOperationSummaries($order, [
        ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => $newQty]],
    ])[0];

    expect($summary)->toBe($description . ': Quantity: ' . $qty . ' → ' . $newQty);
});

it('leaves an operation without a summary when it changes nothing', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    expect(orderOperationSummaries($order, [
        ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => $lineItem->qty]],
        ['type' => 'recalculate'],
    ]))->toBe([null, null]);
});

it('stops summarising at an operation that no longer applies', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    expect(orderOperationSummaries($order, [
        ['type' => 'removeLineItem', 'lineItemUid' => '00000000-0000-4000-8000-000000000000'],
        ['type' => 'setReference', 'reference' => 'PO-9'],
    ]))->toBe([null, null]);
});

it('says which address a change is about in its label', function() {
    $labels = fn(array $operation) => app(OrderOperations::class)->operationFromArray($operation)->label();

    expect($labels(['type' => 'setAddress', 'addressType' => 'billing', 'address' => ['addressLine1' => '5 Elm St']]))->toBe('Change Billing Address')
        ->and($labels(['type' => 'removeAddress', 'addressType' => 'shipping']))->toBe('Remove Shipping Address')
        ->and($labels(['type' => 'copyAddress', 'from' => 'billing']))->toBe('Copy Billing Address to Shipping Address');
});

it('summarises an address change as the fields that change', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $address = $order->getBillingAddress();
    $attributes = OrderAddressType::attributesOf($address);
    $expected = implode(' · ', [
        $address->getAttributeLabel('locality') . ': ' . $address->locality . ' → Salem',
        $address->getAttributeLabel('addressLine1') . ': ' . $address->addressLine1 . ' → 5 Elm St',
    ]);

    $summaries = orderOperationSummaries($order, [
        ['type' => 'setAddress', 'addressType' => 'billing', 'address' => [...$attributes, 'addressLine1' => '5 Elm St', 'locality' => 'Salem']],
    ]);

    expect($summaries[0])->toBe($expected)->not->toContain('Billing');
});

it('names an address field that changes even when the rest of the address reads the same', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $address = $order->getBillingAddress();
    $attributes = OrderAddressType::attributesOf($address);
    $expected = $address->getAttributeLabel('postalCode') . ': ' . $address->postalCode . ' → !!!';

    $summaries = orderOperationSummaries($order, [
        ['type' => 'setAddress', 'addressType' => 'billing', 'address' => [...$attributes, 'postalCode' => '!!!']],
    ]);

    expect($summaries[0])->toBe($expected);
});

it('shows the address before and after when there is no address to compare', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $order->setShippingAddress(null);

    $summaries = orderOperationSummaries($order, [
        ['type' => 'setAddress', 'addressType' => 'shipping', 'address' => ['addressLine1' => '5 Elm St', 'locality' => 'Salem', 'countryCode' => 'US']],
    ]);

    expect($summaries[0])->toBe('None → 5 Elm St, Salem, US');
});

it('summarises a line item change by only the fields that change', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];
    $description = $lineItem->getDescription();
    $qty = $lineItem->qty;

    $summary = orderOperationSummaries($order, [
        ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => [
            'qty' => $qty + 1,
            'price' => $lineItem->getPrice(),
            'taxCategoryId' => $lineItem->taxCategoryId,
            'shippingCategoryId' => $lineItem->shippingCategoryId,
        ]],
    ])[0];

    expect($summary)->toBe($description . ': Quantity: ' . $qty . ' → ' . ($qty + 1));
});

it('lists every field a line item change alters', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];
    $qty = $lineItem->qty;
    $price = $lineItem->getPrice();

    $summary = orderOperationSummaries($order, [
        ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => $qty + 1, 'price' => $price + 5]],
    ])[0];

    expect($summary)->toContain('Quantity: ' . $qty . ' → ' . ($qty + 1) . ' · Price: ');
});

it('drops a line item edit that changes nothing', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    $edit = (new OrderEdit([]))->with(
        app(OrderOperations::class)->operationFromArray(['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => [
            'qty' => $lineItem->qty,
            'price' => $lineItem->getPrice(),
            'taxCategoryId' => $lineItem->taxCategoryId,
            'shippingCategoryId' => $lineItem->shippingCategoryId,
        ]]),
        $order,
    );

    expect($edit->operations)->toBe([]);
});

it('names the field in a validation message instead of its key', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    expect(fn() => app(OrderOperations::class)->operationFromArray(['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => 0]]))
        ->toThrow(ValidationException::class, 'The Quantity field must be at least 1.');
});
