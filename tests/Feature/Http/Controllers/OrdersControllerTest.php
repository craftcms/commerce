<?php

declare(strict_types=1);

use CraftCms\Aliases\Aliases;
use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\View\TemplateMode;
use CraftCms\Cms\View\TemplateRoots;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Events\MailSent;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Gateway\Gateway;
use CraftCms\Commerce\Payment\Gateway\Gateways;
use CraftCms\Commerce\Payment\Gateway\Types\Dummy;
use CraftCms\Commerce\Payment\Models\Transaction as TransactionRecord;
use CraftCms\Commerce\Payment\Transactions;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use CraftCms\Commerce\Shipping\Events\ShippingMethodsResolving;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Event;
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

/** @return array{order: array<string, mixed>} */
function ordersControllerPayload(Order $order, array $overrides = []): array
{
    return [
        'order' => array_merge([
            'id' => $order->id,
            'recalculationMode' => Order::RECALCULATION_MODE_ALL,
            'reference' => $order->reference,
            'customerId' => $order->getCustomerId(),
            'couponCode' => $order->couponCode,
            'isCompleted' => $order->isCompleted,
            'orderStatusId' => $order->orderStatusId,
            'orderSiteId' => $order->orderSiteId,
            'message' => $order->message,
            'shippingMethodHandle' => $order->shippingMethodHandle,
            'shippingMethodName' => $order->shippingMethodName,
            'notices' => [],
            'dateOrdered' => null,
            'lineItems' => [],
            'orderAdjustments' => [],
        ], $overrides),
    ];
}

it('searches for customers by email', function() {
    OrdersFixture::seed();

    $response = get(Url::actionUrl('commerce/orders/customer-search', [
        'query' => 'customer1',
    ]), ['Accept' => 'application/json'])->assertOk();

    $customers = $response->json('customers');
    expect($customers)->toHaveCount(1);

    foreach (['cpEditUrl', 'email', 'id', 'photo', 'status', 'totalAddresses'] as $key) {
        expect($customers[0])->toHaveKey($key);
    }

    expect($customers[0]['email'])->toBe('customer1@crafttest.com');
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

it('returns matching shipping method options for an order', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $response = postJson(
        Url::actionUrl('commerce/orders/get-shipping-method-options'),
        ordersControllerPayload($order)
    )->assertOk();

    $options = $response->json('shippingMethodOptions');
    expect($options)->not->toBeEmpty();

    $option = reset($options);
    foreach (['handle', 'name', 'matchesOrder'] as $key) {
        expect($option)->toHaveKey($key);
    }
});

it('fails with a bad request when given an invalid order id', function() {
    postJson(Url::actionUrl('commerce/orders/get-shipping-method-options'), [
        'order' => ['id' => 999999],
    ])->assertStatus(400);
});

it('includes a custom runtime shipping method registered via the shipping methods event', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $customMethod = Mockery::mock(ShippingMethodInterface::class);
    $customMethod->shouldReceive('getId')->andReturn(null);
    $customMethod->shouldReceive('getName')->andReturn('My Custom Carrier');
    $customMethod->shouldReceive('getHandle')->andReturn('myCustomCarrier');
    $customMethod->shouldReceive('getIsEnabled')->andReturn(true);
    $customMethod->shouldReceive('getPriceForOrder')->andReturn(0.0);
    $customMethod->shouldReceive('matchOrder')->andReturn(true);

    Event::listen(ShippingMethodsResolving::class, function(ShippingMethodsResolving $event) use ($customMethod) {
        $event->shippingMethods->push($customMethod);
    });

    $response = postJson(
        Url::actionUrl('commerce/orders/get-shipping-method-options'),
        ordersControllerPayload($order)
    )->assertOk();

    $options = $response->json('shippingMethodOptions');

    expect($options)->toHaveKey('myCustomCarrier');
    expect($options['myCustomCarrier']['name'])->toBe('My Custom Carrier');
    expect($options['myCustomCarrier']['handle'])->toBe('myCustomCarrier');
});

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

function ordersControllerUserAddress(User $user): Address
{
    $address = new Address();
    $address->setPrimaryOwner($user);
    $address->title = 'Home';
    $address->addressLine1 = '23 Woodworth';
    $address->locality = 'County Island';
    $address->postalCode = '12345';
    $address->countryCode = 'US';

    if (!Elements::saveElement($address)) {
        throw new RuntimeException('Could not save address: ' . json_encode($address->errors()->all()));
    }

    return $address;
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

it('returns no customers when no search query is given', function() {
    OrdersFixture::seed();

    $response = getJson(Url::actionUrl('commerce/orders/customer-search'))->assertOk();

    expect($response->json())->toBe([]);
});

it('creates a customer from an email address', function() {
    $response = postJson(Url::actionUrl('commerce/orders/create-customer'), [
        'email' => 'new-customer@crafttest.com',
    ])->assertOk();

    expect($response->json('user.email'))->toBe('new-customer@crafttest.com');
    expect(User::find()->email('new-customer@crafttest.com')->status(null)->exists())->toBeTrue();
});

it('requires an email to create a customer', function() {
    postJson(Url::actionUrl('commerce/orders/create-customer'), [])->assertStatus(400);
});

it('lists a customer’s addresses with their card html', function() {
    $fixture = OrdersFixture::seed();
    $address = ordersControllerUserAddress($fixture->customer);

    $response = getJson(Url::actionUrl('commerce/orders/get-customer-addresses', [
        'id' => $fixture->customer->id,
    ]))->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('addresses.0.id'))->toBe($address->id);
    expect($response->json('addresses.0.html'))->toBeString()->not->toBeEmpty();
});

it('fails to list addresses for an unknown customer', function() {
    getJson(Url::actionUrl('commerce/orders/get-customer-addresses', ['id' => 999999]))
        ->assertStatus(400);
});

it('returns one of the order’s own addresses', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $address = $order->getShippingAddress();

    $response = getJson(Url::actionUrl('commerce/orders/get-order-address', [
        'orderId' => $order->id,
        'addressId' => $address->id,
    ]))->assertOk();

    expect($response->json('address.id'))->toBe($address->id);
    expect($response->json('address.html'))->toBeString()->not->toBeEmpty();
});

it('does not return an address the order does not own', function() {
    $fixture = OrdersFixture::seed();
    $address = ordersControllerUserAddress($fixture->customer);

    getJson(Url::actionUrl('commerce/orders/get-order-address', [
        'orderId' => $fixture->orders['completed-new']->id,
        'addressId' => $address->id,
    ]))->assertStatus(400);
});

it('validates an address', function() {
    postJson(Url::actionUrl('commerce/orders/validate-address'), [
        'address' => ['title' => 'Home', 'countryCode' => 'US', 'addressLine1' => '1 Main St', 'locality' => 'Springfield', 'administrativeArea' => 'IL', 'postalCode' => '62701'],
    ])->assertOk();

    $response = postJson(Url::actionUrl('commerce/orders/validate-address'), [
        'address' => ['countryCode' => 'US'],
    ])->assertStatus(400);

    expect($response->json('errors'))->not->toBeEmpty();
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

it('sends an order email', function() {
    $templatesPath = dirname(__DIR__, 3) . '/Support/templates';
    Aliases::set('@templates', $templatesPath);
    app(TemplateRoots::class)->register(TemplateMode::Site, '', $templatesPath);

    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $email = new Email();
    $email->storeId = $fixture->storeId;
    $email->name = 'Order Confirmation';
    $email->subject = 'Order Confirmation';
    $email->templatePath = 'emails/order-confirmation';
    $email->setSenderAddress('store@crafttest.com');
    app(Emails::class)->saveEmail($email);

    $sent = [];
    Event::listen(MailSent::class, function(MailSent $event) use (&$sent) {
        $sent[] = $event;
    });

    postJson(Url::actionUrl('commerce/orders/send-email'), [
        'id' => $email->id,
        'orderId' => $order->id,
    ])->assertOk();

    expect($sent)->toHaveCount(1);
});

it('fails to send an email that does not exist', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/send-email'), [
        'id' => 999999,
        'orderId' => $order->id,
    ])->assertStatus(400);
});

it('deletes an order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/delete-order'), ['orderId' => $order->id])->assertOk();

    expect(Order::find()->id($order->id)->exists())->toBeFalse();
});

it('forbids deleting an order without the delete orders permission', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    Gate::before(fn($user, $ability) => $ability === 'commerce-deleteOrders' ? false : null);

    postJson(Url::actionUrl('commerce/orders/delete-order'), ['orderId' => $order->id])->assertForbidden();

    expect(Order::find()->id($order->id)->exists())->toBeTrue();
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

it('renders the fulfillment modal', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    $response = getJson(Url::actionUrl('commerce/orders/fulfillment-modal', ['orderId' => $order->id]), ['X-Craft-Container-Id' => 'fulfillment'])->assertOk();

    expect($response->json('action'))->toBe('commerce/orders/fulfill');
});

it('fulfills committed stock on an order', function() {
    $fixture = OrdersFixture::seed();
    $white = $fixture->white;
    $white->inventoryTracked = true;
    Elements::saveElement($white, false);
    app(Inventory::class)->updatePurchasableInventoryLevel($white, 10);

    $order = new Order();
    $order->number = bin2hex(random_bytes(16));
    $order->storeId = $fixture->storeId;
    $order->setCustomerId($fixture->customer->id);
    Elements::saveElement($order, false);
    $order->setLineItems([app(LineItems::class)->create($order, ['purchasableId' => $white->id, 'qty' => 3])]);
    Elements::saveElement($order, false);
    $order->markAsComplete();

    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();
    expect($level->outstandingCommittedQuantity)->toBe(3);

    postJson(Url::actionUrl('commerce/orders/fulfill'), [
        'fulfillment' => [[
            'inventoryLocationId' => $level->inventoryLocationId,
            'inventoryItemId' => $level->inventoryItemId,
            'lineItemId' => $level->lineItemId,
            'quantity' => 2,
        ]],
    ])->assertOk();

    $level = app(Inventory::class)->getInventoryFulfillmentLevels($order)->first();

    expect($level->fulfilledQuantity)->toBe(2);
    expect($level->outstandingCommittedQuantity)->toBe(1);
});

it('rejects an invalid address type when updating an order address', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    get(Url::actionUrl('commerce/orders/update-order-address', [
        'orderId' => $order->id,
        'addressId' => 1,
        'addressType' => 'id',
    ]), ['Accept' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Not a valid address type');
});

it('rejects an unknown order when updating an order address', function() {
    get(Url::actionUrl('commerce/orders/update-order-address', [
        'orderId' => 999999,
        'addressId' => 1,
        'addressType' => 'shippingAddress',
    ]), ['Accept' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Bad order ID.');
});
