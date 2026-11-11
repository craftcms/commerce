<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function totalsExpected(Order $order): array
{
    $subtotal = 0.0;
    foreach ($order->getLineItems() as $lineItem) {
        $subtotal += round(($lineItem->getPromotionalPrice() ?? $lineItem->getPrice()) * $lineItem->qty, 2);
    }

    $adjustments = 0.0;
    foreach ($order->getAdjustments() as $adjustment) {
        if (!$adjustment->included) {
            $adjustments += $adjustment->amount;
        }
    }

    return [round($subtotal, 2), round($subtotal + $adjustments, 2)];
}

function totalsRecord(Order $order, string $action, float $amount): void
{
    postJson(Url::actionUrl("commerce/orders/payment/$action"), [
        'orderId' => $order->id,
        'amount' => ['value' => (string)$amount, 'locale' => 'en-US'],
        'note' => 'Balance check',
        'confirmed' => true,
    ])->assertOk();
}

function totalsCommit(Order $order, array $operations): Order
{
    $orderId = $order->id;
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $orderId])->assertOk();

    foreach ($operations as $operation) {
        postJson(Url::actionUrl('commerce/orders/edit/add'), ['orderId' => $orderId, 'operation' => $operation])->assertOk();
    }

    postJson(Url::actionUrl('commerce/orders/edit/commit'), ['orderId' => $orderId])->assertOk();

    return Order::find()->id($orderId)->one();
}

it('keeps the saved totals equal to the line items and adjustments after each change', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $blue = collect($order->getLineItems())->first(fn($lineItem) => $lineItem->purchasableId === $fixture->blue->id);
    $steps = [
        [['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id, 'qty' => 2]],
        [['type' => 'updateLineItem', 'lineItemUid' => $blue->uid, 'changes' => ['qty' => 7]]],
        [['type' => 'removeLineItem', 'lineItemUid' => $blue->uid]],
        [['type' => 'recalculateCharges']],
    ];

    foreach ($steps as $operations) {
        $order = totalsCommit($order, $operations);
        [$subtotal, $total] = totalsExpected($order);

        expect($order->getItemSubtotal())->toEqual($subtotal)
            ->and($order->getTotalPrice())->toEqual(max(0.0, $total));
    }
});

it('gives a full recalculation the same totals as the edited order once charges are regenerated', function() {
    $fixture = OrdersFixture::seed();
    $order = totalsCommit($fixture->orders['completed-new'], [
        ['type' => 'addLineItem', 'uid' => (string)Str::uuid(), 'purchasableId' => $fixture->white->id, 'qty' => 3],
        ['type' => 'recalculateCharges'],
    ]);
    $edited = [$order->getItemSubtotal(), $order->getTotalPrice()];

    $recalculated = totalsCommit($order, [['type' => 'recalculate']]);

    expect([$recalculated->getItemSubtotal(), $recalculated->getTotalPrice()])->toEqual($edited);
});

it('keeps the outstanding balance at the total minus the net payments through payments, a refund and an item change', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $assertBalance = function(Order $order, float $netPaid) {
        $fresh = Order::find()->id($order->id)->one();

        expect($fresh->getTotalPaid())->toEqual($netPaid)
            ->and($fresh->getOutstandingBalance())->toEqual(round($fresh->getTotalPrice() - $netPaid, 2));

        return $fresh;
    };

    $order = $assertBalance($order, 0.0);

    totalsRecord($order, 'record-payment', 10.0);
    $order = $assertBalance($order, 10.0);

    totalsRecord($order, 'record-payment', 25.5);
    $order = $assertBalance($order, 35.5);

    totalsRecord($order, 'record-refund', 5.5);
    $order = $assertBalance($order, 30.0);

    $line = $order->getLineItems()[0];
    $order = totalsCommit($order, [['type' => 'updateLineItem', 'lineItemUid' => $line->uid, 'changes' => ['qty' => $line->qty + 2]]]);
    $assertBalance($order, 30.0);
});
