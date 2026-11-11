<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Data\OrderAdjustment;
use CraftCms\Commerce\Order\Edit\Exceptions\OperationNotApplicableException;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

/** @param list<array<string, mixed>> $operations */
function chargesApply(Order $order, array $operations): void
{
    $operations = app(OrderOperations::class)->editFromArray(['version' => OrderEdit::VERSION, 'operations' => $operations])->operations;
    app(OrderOperations::class)->apply($order, $operations);
}

function chargesOfType(Order $order, string $type): array
{
    return array_values(array_filter($order->getAdjustments() ?? [], fn(OrderAdjustment $adjustment) => $adjustment->type === $type));
}

function chargesStale(Order $order): void
{
    $stale = new OrderAdjustment();
    $stale->setOrder($order);
    $stale->type = 'tax';
    $stale->name = 'Old tax';
    $stale->amount = 9.0;
    $stale->setSourceSnapshot(['id' => 1]);
    $order->setAdjustments([...$order->getAdjustments() ?? [], $stale]);
}

it('replaces generated adjustments and keeps manual adjustments and prices', function() {
    $order = Order::find()->id(OrdersFixture::seed()->orders['completed-new']->id)->one();
    $lineItem = $order->getLineItems()[0];
    chargesStale($order);
    chargesApply($order, [
        ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['price' => 1.23]],
        ['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -2],
    ]);

    chargesApply($order, [['type' => 'recalculateCharges']]);

    $names = collect($order->getAdjustments())->pluck('name');

    expect($names)->not->toContain('Old tax')
        ->and($names)->toContain('Goodwill')
        ->and($order->getLineItems()[0]->getPrice())->toBe(1.23);
});

it('marks only adjustments added from the edit page as manual', function() {
    $order = Order::find()->id(OrdersFixture::seed()->orders['completed-new']->id)->one();
    chargesStale($order);

    chargesApply($order, [['type' => 'addAdjustment', 'adjustmentType' => 'discount', 'name' => 'Goodwill', 'amount' => -2]]);

    expect(collect($order->getAdjustments())->filter(fn(OrderAdjustment $a) => $a->isManual())->pluck('name')->all())->toBe(['Goodwill']);
});

it('only recalculates charges on completed orders', function() {
    $cart = new Order();
    $cart->isCompleted = false;

    expect(fn() => app(OrderOperations::class)->apply($cart, [new CraftCms\Commerce\Order\Edit\Operations\RecalculateCharges()]))
        ->toThrow(OperationNotApplicableException::class);
});

it('tells the page when charges are being kept after an address, method or coupon change', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $path = '/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}";
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), ['orderId' => $order->id, 'operation' => ['type' => 'setReference', 'reference' => 'PO-1']])->assertOk();

    get($path)->assertInertia(fn(AssertableInertia $page) => $page->where('session.chargesKept', false)->etc());

    postJson(Url::actionUrl('commerce/orders/edit/add'), ['orderId' => $order->id, 'operation' => ['type' => 'setCouponCode', 'couponCode' => 'SAVE10']])->assertOk();

    get($path)->assertInertia(fn(AssertableInertia $page) => $page
        ->where('session.chargesKept', true)
        ->where('session.operations', fn($operations) => collect($operations)->firstWhere('label', 'Change Coupon Code')['note'] !== null
            && collect($operations)->firstWhere('label', 'Change Reference')['note'] === null)
        ->etc()
    );

    postJson(Url::actionUrl('commerce/orders/edit/add'), ['orderId' => $order->id, 'operation' => ['type' => 'recalculateCharges']])->assertOk();

    get($path)->assertInertia(fn(AssertableInertia $page) => $page->where('session.chargesKept', false)->etc());
});

it('tells the page when charges are being kept after a line item change', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];
    $path = '/' . Cms::config()->cpTrigger . "/commerce/orders/{$order->id}";
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'updateLineItem', 'lineItemUid' => $lineItem->uid, 'changes' => ['qty' => $lineItem->qty + 1]],
    ])->assertOk();

    get($path)->assertInertia(fn(AssertableInertia $page) => $page
        ->where('session.chargesKept', true)
        ->where('session.operations', fn($operations) => collect($operations)->firstWhere('label', 'Update Line Item')['note'] !== null)
        ->etc()
    );
});
