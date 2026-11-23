<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Edit\OrderEditSessions;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Tests\Support\OrdersFixture;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function lifecycleCart(OrdersFixture $fixture): Order
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    return Order::find()->id($cart->id)->one();
}

function lifecycleStatusUrl(): string
{
    return Url::actionUrl('commerce/orders/edit/status');
}

function lifecycleOtherAdmin(): User
{
    $user = new User();
    $user->username = 'lifecycle-' . Str::random(6);
    $user->email = $user->username . '@crafttest.com';
    $user->admin = true;
    Elements::saveElement($user);

    return $user;
}

it('offers the store’s statuses on the status form, with the current one selected', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    getJson(lifecycleStatusUrl() . '?orderId=' . $order->id)
        ->assertOk()
        ->assertJsonPath('title', 'Change Status')
        ->assertJsonPath('ui.values.orderStatusId', (string)$order->orderStatusId)
        ->assertJsonPath('ui.values.suppressEmails', false);
});

it('changes a completed order’s status with a message', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    postJson(lifecycleStatusUrl(), [
        'orderId' => $order->id,
        'orderStatusId' => $fixture->shippedOrderStatusId,
        'message' => 'Left the warehouse',
        'suppressEmails' => true,
    ])->assertOk();

    expect(Order::find()->id($order->id)->one()->orderStatusId)->toBe($fixture->shippedOrderStatusId);
});

it('rejects a status that doesn’t exist', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(lifecycleStatusUrl(), ['orderId' => $order->id, 'orderStatusId' => 999999])
        ->assertStatus(422)
        ->assertJsonPath('errors.orderStatusId.0', 'The status doesn’t exist.');
});

it('doesn’t give a cart a status', function() {
    $cart = lifecycleCart(OrdersFixture::seed());

    getJson(lifecycleStatusUrl() . '?orderId=' . $cart->id)->assertStatus(400);
    postJson(lifecycleStatusUrl(), ['orderId' => $cart->id, 'orderStatusId' => 1])->assertStatus(400);
});

it('completes a cart with the store’s default status', function() {
    $cart = lifecycleCart(OrdersFixture::seed());

    postJson(Url::actionUrl('commerce/orders/edit/complete'), ['orderId' => $cart->id])->assertOk();

    $completed = Order::find()->id($cart->id)->one();
    expect($completed->isCompleted)->toBeTrue()
        ->and($completed->orderStatusId)->not->toBeNull();
});

it('doesn’t complete an order twice', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/edit/complete'), ['orderId' => $order->id])->assertStatus(400);
});

it('refuses while the order’s edit session has changes waiting', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    postJson(lifecycleStatusUrl(), ['orderId' => $order->id, 'orderStatusId' => $fixture->shippedOrderStatusId])->assertOk();

    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'setReference', 'reference' => 'PO-LIFECYCLE'],
    ])->assertOk();

    postJson(lifecycleStatusUrl(), ['orderId' => $order->id, 'orderStatusId' => $fixture->shippedOrderStatusId])
        ->assertStatus(423)
        ->assertJsonPath('message', 'Update or discard your changes to this order first.');

    getJson(lifecycleStatusUrl() . '?orderId=' . $order->id)->assertStatus(423);

    postJson(Url::actionUrl('commerce/orders/edit/discard'), ['orderId' => $order->id])->assertOk();

    postJson(lifecycleStatusUrl(), ['orderId' => $order->id, 'orderStatusId' => $fixture->shippedOrderStatusId])->assertOk();
});

it('refuses while someone else is editing the order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $editor = lifecycleOtherAdmin();

    $sessions = app(OrderEditSessions::class);
    $session = $sessions->start($order, $editor);
    $sessions->addOperation($session, $order, app(CraftCms\Commerce\Order\Edit\OrderOperations::class)->operationFromArray(['type' => 'setReference', 'reference' => 'PO-OTHER']));

    postJson(lifecycleStatusUrl(), ['orderId' => $order->id, 'orderStatusId' => $fixture->shippedOrderStatusId])
        ->assertStatus(423)
        ->assertJsonPath('message', fn(string $message) => str_starts_with($message, 'This order is being edited by'));
});
