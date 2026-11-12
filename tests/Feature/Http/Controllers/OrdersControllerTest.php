<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
    $this->withoutVite();
});

it('returns order counts per status for the index source badges', function() {
    $fixture = OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/get-index-sources-badge-counts'), ['Accept' => 'application/json'])
        ->assertOk();

    $counts = $response->json('counts');

    expect($counts)->not->toBeEmpty();
    expect($response->json('total'))->toBe(count($fixture->orders));

    $firstCount = collect($counts)->first();
    foreach (['orderStatusId', 'handle', 'orderCount'] as $key) {
        expect($firstCount)->toHaveKey($key);
    }

    $shippedCount = collect($counts)->firstWhere('handle', 'shipped');
    expect($shippedCount['orderCount'])->toBe(1);
})->group('yii2-adapter'); // TODO: remove from the yii2-adapter group once OrdersController is migrated

function ordersControllerDummyGateway(Order $order): Gateway
{
    $gateways = app(Gateways::class);
    $gateway = $gateways->createGateway([
        'type' => Dummy::class,
        'name' => 'Dummy',
        'handle' => 'orderEditDummy',
        'isFrontendEnabled' => true,
    ]);

    if (!$gateways->saveGateway($gateway)) {
        throw new RuntimeException('Could not save gateway: ' . json_encode($gateway->errors()->all()));
    }

    $order->gatewayId = $gateway->id;
    Elements::saveElement($order, false);

    return $gateway;
}

function ordersControllerTransaction(Order $order, string $type, ?Transaction $parent = null): Transaction
{
    $transactions = app(Transactions::class);
    $transaction = $transactions->createTransaction($parent ? null : $order, $parent, $type);
    $transaction->status = TransactionRecord::STATUS_SUCCESS;
    $transaction->reference = 'ref-' . $type;
    $transactions->saveTransaction($transaction);

    return $transaction;
}

it('creates a control panel order for a store and redirects to it', function() {
    $fixture = OrdersFixture::seed();
    $store = app(Stores::class)->getPrimaryStore();

    $response = get(Url::cpUrl("commerce/orders/$store->handle/create", ['customerId' => $fixture->customer->id]))
        ->assertRedirect();

    $order = Order::find()->isCompleted(false)->orderBy('id desc')->one();

    expect($response->headers->get('Location'))->toBe(Url::cpUrl('commerce/orders/' . $order->id));
    expect($order->origin)->toBe(Order::ORIGIN_CP);
    expect($order->storeId)->toBe($store->id);
    expect($order->getCustomerId())->toBe($fixture->customer->id);
});

it('rejects creating an order for an unknown store or customer', function() {
    $store = app(Stores::class)->getPrimaryStore();

    get(Url::cpUrl('commerce/orders/not-a-store/create'))->assertStatus(400);
    get(Url::cpUrl("commerce/orders/$store->handle/create", ['customerId' => 999999]))->assertStatus(400);
});

it('returns not found when editing a missing order', function() {
    get(Url::cpUrl('commerce/orders/999999'))->assertNotFound();
});

it('forbids the order edit page without the manage orders permission', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageOrders' ? false : null);

    get(Url::cpUrl("commerce/orders/$order->id"))->assertForbidden();
});

it('copies an order address to the customer', function() {
    $fixture = OrdersFixture::seed();
    $fixture->customer->password = 'password';
    Elements::saveElement($fixture->customer, false);
    $orderAddress = $fixture->orders['completed-new']->getShippingAddress();

    $response = postJson(Url::actionUrl('commerce/orders/copy-address-to-user'), [
        'addressId' => $orderAddress->id,
        'userId' => $fixture->customer->id,
    ])->assertOk();

    $copy = Address::find()->id($response->json('address.id'))->one();

    expect($copy->id)->not->toBe($orderAddress->id);
    expect($copy->getOwnerId())->toBe($fixture->customer->id);
    expect($copy->addressLine1)->toBe($orderAddress->addressLine1);
});

it('does not copy an address to a user without credentials', function() {
    $fixture = OrdersFixture::seed();
    $guest = User::find()->email('guest@crafttest.com')->status(null)->one()
        ?? \CraftCms\Cms\Support\Facades\Users::ensureUserByEmail('guest@crafttest.com');

    postJson(Url::actionUrl('commerce/orders/copy-address-to-user'), [
        'addressId' => $fixture->orders['completed-new']->getShippingAddress()->id,
        'userId' => $guest->id,
    ])->assertStatus(400);
});

it('returns the load cart url for a cart', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $order->storeId;
    Elements::saveElement($cart, false);

    $response = getJson(Url::actionUrl('commerce/orders/get-load-cart-url', ['id' => $cart->id]))->assertOk();

    expect($response->json('url'))->toContain($cart->number);

    getJson(Url::actionUrl('commerce/orders/get-load-cart-url', ['id' => $order->id]))->assertNotFound();
});

it('records a capture the gateway declines and reports the failure', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    ordersControllerDummyGateway($order);
    $authorize = ordersControllerTransaction($order, TransactionRecord::TYPE_AUTHORIZE);

    postJson(Url::actionUrl('commerce/orders/transaction-capture'), ['id' => $authorize->id])->assertStatus(400);

    $children = app(Transactions::class)->getChildrenByTransactionId($authorize->id);

    expect($children)->toHaveCount(1);
    expect($children[0]->type)->toBe(TransactionRecord::TYPE_CAPTURE);
    expect($children[0]->status)->toBe(TransactionRecord::STATUS_FAILED);
});

it('forbids capturing without the capture payment permission', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    ordersControllerDummyGateway($order);
    $authorize = ordersControllerTransaction($order, TransactionRecord::TYPE_AUTHORIZE);
    Gate::before(fn($user, $ability) => $ability === 'commerce-capturePayment' ? false : null);

    postJson(Url::actionUrl('commerce/orders/transaction-capture'), ['id' => $authorize->id])->assertForbidden();
});

it('refunds part of a purchase transaction', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    ordersControllerDummyGateway($order);
    $purchase = ordersControllerTransaction($order, TransactionRecord::TYPE_PURCHASE);

    postJson(Url::actionUrl('commerce/orders/transaction-refund'), [
        'id' => $purchase->id,
        'amount' => ['value' => '5', 'locale' => 'en-US'],
        'note' => 'Goodwill',
    ])->assertOk();

    $refund = collect(app(Transactions::class)->getChildrenByTransactionId($purchase->id))
        ->firstWhere('type', TransactionRecord::TYPE_REFUND);

    expect((float)$refund->amount)->toBe(5.0);
    expect($refund->note)->toBe('Goodwill');
});

it('does not refund more than the refundable amount', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    ordersControllerDummyGateway($order);
    $purchase = ordersControllerTransaction($order, TransactionRecord::TYPE_PURCHASE);

    postJson(Url::actionUrl('commerce/orders/transaction-refund'), [
        'id' => $purchase->id,
        'amount' => ['value' => (string)($purchase->amount + 1), 'locale' => 'en-US'],
        'note' => '',
    ])->assertStatus(400);
});

it('requires a note to refund', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    ordersControllerDummyGateway($order);
    $purchase = ordersControllerTransaction($order, TransactionRecord::TYPE_PURCHASE);

    postJson(Url::actionUrl('commerce/orders/transaction-refund'), [
        'id' => $purchase->id,
        'amount' => ['value' => '5', 'locale' => 'en-US'],
    ])->assertStatus(400);
});
