<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Customer\Customers;
use CraftCms\Commerce\Email\Data\Email;
use CraftCms\Commerce\Email\Emails;
use CraftCms\Commerce\Email\Jobs\SendEmailJob;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;
use CraftCms\Commerce\Order\LineItem\Enums\LineItemType;
use CraftCms\Commerce\Order\LineItem\LineItems;
use CraftCms\Commerce\Order\OrderHistories;
use CraftCms\Commerce\Order\OrderStatuses;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * Pins down how today's order edit screen applies a draft (`orders/refresh` previews it,
 * `orders/save` persists it), so the redesign's order operations can be held to the same
 * behaviour.
 */
beforeEach(function() {
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

/**
 * Builds the draft payload the order edit screen submits for the order as it currently is.
 *
 * @param array<string, mixed> $overrides
 * @return array{order: array<string, mixed>}
 */
function orderEditDraft(Order $order, array $overrides = []): array
{
    $address = static fn(?Address $address): ?array => $address ? [
        'id' => $address->id,
        'ownerId' => $address->getOwnerId(),
    ] : null;

    return [
        'order' => array_merge([
            'id' => $order->id,
            'recalculationMode' => $order->getRecalculationMode(),
            'reference' => $order->reference,
            'customerId' => $order->getCustomerId(),
            'couponCode' => $order->couponCode,
            'isCompleted' => $order->isCompleted,
            'orderStatusId' => $order->orderStatusId,
            'orderSiteId' => $order->orderSiteId,
            'message' => null,
            'shippingMethodHandle' => $order->shippingMethodHandle,
            'shippingMethodName' => $order->shippingMethodName,
            'billingAddress' => $address($order->getBillingAddress()),
            'shippingAddress' => $address($order->getShippingAddress()),
            'notices' => [],
            'adminNotices' => [],
            'dateOrdered' => $order->dateOrdered ? [
                'date' => $order->dateOrdered->format('Y-m-d'),
                'time' => $order->dateOrdered->format('H:i'),
                'timezone' => $order->dateOrdered->getTimezone()->getName(),
            ] : null,
            'lineItems' => array_map(orderEditDraftLineItem(...), $order->getLineItems()),
            'orderAdjustments' => [],
        ], $overrides),
    ];
}

/** @return array<string, mixed> */
function orderEditDraftLineItem(LineItem $lineItem, array $overrides = []): array
{
    return array_merge([
        'id' => $lineItem->id,
        'type' => $lineItem->type->value,
        'purchasableId' => $lineItem->purchasableId,
        'lineItemStatusId' => $lineItem->lineItemStatusId,
        'qty' => $lineItem->qty,
        'note' => $lineItem->note,
        'privateNote' => $lineItem->privateNote,
        'options' => $lineItem->getOptions(),
        'uid' => $lineItem->uid,
        'price' => $lineItem->price,
        'promotionalPrice' => $lineItem->promotionalPrice,
        'adjustments' => [],
    ], $overrides);
}

/** @return array<string, mixed> */
function orderEditDraftAdjustment(array $overrides = []): array
{
    return array_merge([
        'id' => null,
        'amount' => -5,
        'type' => 'discount',
        'name' => 'Goodwill',
        'description' => 'Sorry for the wait',
        'included' => false,
        'sourceSnapshot' => [],
    ], $overrides);
}

function orderEditCart(OrdersFixture $fixture, int $qty = 1): Order
{
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    $cart->setCustomerId($fixture->customer->id);
    Elements::saveElement($cart, false);

    $cart->setLineItems([
        app(LineItems::class)->create($cart, ['purchasableId' => $fixture->white->id, 'qty' => $qty]),
    ]);
    Elements::saveElement($cart, false);

    return Order::find()->id($cart->id)->one();
}

function freshOrder(Order $order): Order
{
    return Order::find()->id($order->id)->one();
}

it('previews a draft without saving it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    $response = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($order, [
        'lineItems' => [orderEditDraftLineItem($lineItem, ['qty' => 7])],
    ]))->assertOk();

    expect($response->json('order.lineItems'))->toHaveCount(1);
    expect($response->json('order.lineItems.0.qty'))->toBe(7);
    expect(freshOrder($order)->getLineItems())->toHaveCount(2);
    expect(freshOrder($order)->getLineItems()[0]->qty)->toBe($lineItem->qty);
});

it('recalculates a cart’s totals when previewing', function() {
    $fixture = OrdersFixture::seed();
    $cart = orderEditCart($fixture);
    $lineItem = $cart->getLineItems()[0];

    $response = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($cart, [
        'lineItems' => [orderEditDraftLineItem($lineItem, ['qty' => 3])],
    ]))->assertOk();

    expect((float)$response->json('order.itemSubtotal'))->toBe(round($fixture->white->getPrice() * 3, 2));
    expect($response->json('order.recalculationMode'))->toBe(Order::RECALCULATION_MODE_ALL);
});

it('keeps a manual price on a completed order unless the draft asks for a full recalculation', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    $manual = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($order, [
        'lineItems' => [orderEditDraftLineItem($lineItem, ['price' => '5'])],
    ]))->assertOk();

    expect((float)$manual->json('order.lineItems.0.price'))->toBe(5.0);
    expect($manual->json('order.recalculationMode'))->toBe(Order::RECALCULATION_MODE_NONE);

    $recalculated = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($order, [
        'recalculationMode' => Order::RECALCULATION_MODE_ALL,
        'lineItems' => [orderEditDraftLineItem($lineItem, ['price' => '5'])],
    ]))->assertOk();

    expect((float)$recalculated->json('order.lineItems.0.price'))->toBe((float)$lineItem->getPurchasable()->getPrice());
    expect($recalculated->json('order.recalculationMode'))->toBe(Order::RECALCULATION_MODE_NONE);
});

it('copies a customer address onto the order when previewing a draft that uses it', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $customerAddress = new Address();
    $customerAddress->setPrimaryOwner($fixture->customer);
    $customerAddress->title = 'Home';
    $customerAddress->addressLine1 = '23 Woodworth';
    $customerAddress->countryCode = 'US';
    Elements::saveElement($customerAddress);

    $addressCount = Address::find()->ownerId($order->id)->count();

    $response = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($order, [
        'shippingAddress' => ['id' => $customerAddress->id, 'ownerId' => $fixture->customer->id],
    ]))->assertOk();

    expect($response->json('order.shippingAddress.addressLine1'))->toBe('23 Woodworth');
    expect($response->json('order.shippingAddress.id'))->not->toBe($customerAddress->id);

    // The preview writes a copy of the address to the database, owned by the order, even though
    // nothing else about the draft is saved. The redesign's previews must not do this.
    expect(Address::find()->ownerId($order->id)->count())->toBe($addressCount + 1);
});

it('auto-sets a cart’s addresses when previewing a customer change without addresses', function() {
    $fixture = OrdersFixture::seed();
    $cart = orderEditCart($fixture);

    $customer = new User();
    $customer->username = 'customer2';
    $customer->email = 'customer2@crafttest.com';
    Elements::saveElement($customer);

    $home = new Address();
    $home->setPrimaryOwner($customer);
    $home->title = 'Home';
    $home->addressLine1 = '1 Customer Two Lane';
    $home->countryCode = 'US';
    Elements::saveElement($home);

    app(Customers::class)->savePrimaryBillingAddressId($customer, $home->id);
    app(Customers::class)->savePrimaryShippingAddressId($customer, $home->id);

    $store = app(Stores::class)->getStoreById($fixture->storeId);
    $store->setAutoSetNewCartAddresses(true);
    app(Stores::class)->saveStore($store);

    $response = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($cart, [
        'customerId' => $customer->id,
        'billingAddress' => null,
        'shippingAddress' => null,
    ]))->assertOk();

    expect($response->json('order.customerId'))->toBe($customer->id);
    expect($response->json('order.shippingAddress.addressLine1'))->toBe('1 Customer Two Lane');
    expect($response->json('order.billingAddress.addressLine1'))->toBe('1 Customer Two Lane');
});

it('reports draft errors from a preview with the order data', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    $response = postJson(Url::actionUrl('commerce/orders/refresh'), orderEditDraft($order, [
        'lineItems' => [[
            'id' => null,
            'type' => LineItemType::Purchasable->value,
            'purchasableId' => 999999,
            'lineItemStatusId' => null,
            'qty' => 1,
            'price' => '1',
            'promotionalPrice' => null,
            'adjustments' => [],
        ]],
    ]))->assertStatus(400);

    expect($response->json('errors.lineItems'))->not->toBeEmpty();
    expect($response->json('order.id'))->toBe($order->id);
});

it('saves manual prices and adjustments on a completed order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    [$white, $blue] = $order->getLineItems();

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'lineItems' => [
            orderEditDraftLineItem($white, [
                'price' => '5',
                'adjustments' => [orderEditDraftAdjustment(['amount' => -1, 'name' => 'Line discount'])],
            ]),
            orderEditDraftLineItem($blue),
        ],
        'orderAdjustments' => [orderEditDraftAdjustment()],
    ]))])->assertRedirect();

    $saved = freshOrder($order);
    $savedWhite = collect($saved->getLineItems())->firstWhere('id', $white->id);

    expect((float)$savedWhite->price)->toBe(5.0);
    expect(collect($saved->getAdjustments())->pluck('name')->all())
        ->toContain('Goodwill')
        ->toContain('Line discount');
    expect((float)collect($saved->getAdjustments())->firstWhere('name', 'Goodwill')->amount)->toBe(-5.0);
    expect(collect($saved->getAdjustments())->firstWhere('name', 'Line discount')->getLineItem()?->id)->toBe($white->id);
    expect($saved->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
});

it('ignores submitted adjustments when the draft recalculates everything', function() {
    $fixture = OrdersFixture::seed();
    $cart = orderEditCart($fixture);

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($cart, [
        'orderAdjustments' => [orderEditDraftAdjustment()],
    ]))])->assertRedirect();

    expect(collect(freshOrder($cart)->getAdjustments())->pluck('name')->all())->not->toContain('Goodwill');
});

it('saves a custom line item', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $taxCategoryId = app(TaxCategories::class)->getDefaultTaxCategory()->id;
    $shippingCategoryId = app(ShippingCategories::class)->getDefaultShippingCategory($order->storeId)->id;

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'lineItems' => [
            ...array_map(orderEditDraftLineItem(...), $order->getLineItems()),
            [
                'id' => null,
                'type' => LineItemType::Custom->value,
                'purchasableId' => null,
                'lineItemStatusId' => null,
                'qty' => 2,
                'description' => 'Gift wrapping',
                'sku' => 'GIFT-WRAP',
                'taxCategoryId' => $taxCategoryId,
                'shippingCategoryId' => $shippingCategoryId,
                'price' => '3.50',
                'promotionalPrice' => null,
                'hasFreeShipping' => true,
                'isPromotable' => false,
                'isShippable' => false,
                'isTaxable' => false,
                'adjustments' => [],
            ],
        ],
    ]))])->assertRedirect();

    $custom = collect(freshOrder($order)->getLineItems())->firstWhere('type', LineItemType::Custom);

    expect($custom)->not->toBeNull();
    expect($custom->getDescription())->toBe('Gift wrapping');
    expect($custom->getSku())->toBe('GIFT-WRAP');
    expect((float)$custom->price)->toBe(3.5);
    expect($custom->qty)->toBe(2);
    expect($custom->taxCategoryId)->toBe($taxCategoryId);
    expect($custom->shippingCategoryId)->toBe($shippingCategoryId);
    expect($custom->getHasFreeShipping())->toBeTrue();
    expect($custom->getIsPromotable())->toBeFalse();
    expect($custom->getIsShippable())->toBeFalse();
    expect($custom->getIsTaxable())->toBeFalse();
});

it('removes line items saved with a quantity of zero', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    [$white, $blue] = $order->getLineItems();

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'lineItems' => [orderEditDraftLineItem($white, ['qty' => 0]), orderEditDraftLineItem($blue)],
    ]))])->assertRedirect();

    expect(collect(freshOrder($order)->getLineItems())->pluck('id')->all())->toBe([$blue->id]);
});

it('records a status change in the order history with its message', function() {
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'orderStatusId' => $fixture->shippedOrderStatusId,
        'message' => 'Shipped with DHL',
    ]))])->assertRedirect();

    $history = collect(app(OrderHistories::class)->getAllOrderHistoriesByOrderId($order->id))
        ->firstWhere('newStatusId', $fixture->shippedOrderStatusId);

    expect(freshOrder($order)->orderStatusId)->toBe($fixture->shippedOrderStatusId);
    expect($history)->not->toBeNull();
    expect($history->message)->toBe('Shipped with DHL');
});

it('sends the new status’s emails unless the draft suppresses them', function(bool $suppressEmails) {
    Queue::fake();
    $fixture = OrdersFixture::seed();
    $order = $fixture->orders['completed-new'];

    $email = new Email();
    $email->storeId = $fixture->storeId;
    $email->name = 'Shipped';
    $email->subject = 'Shipped';
    $email->templatePath = 'emails/order-confirmation';
    app(Emails::class)->saveEmail($email);

    $shipped = app(OrderStatuses::class)->getOrderStatusById($fixture->shippedOrderStatusId, $fixture->storeId);
    app(OrderStatuses::class)->saveOrderStatus($shipped, [$email->id]);

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'orderStatusId' => $fixture->shippedOrderStatusId,
        'suppressEmails' => $suppressEmails,
    ]))])->assertRedirect();

    $suppressEmails
        ? Queue::assertNotPushed(SendEmailJob::class)
        : Queue::assertPushed(SendEmailJob::class);
})->with([
    'sent' => false,
    'suppressed' => true,
]);

it('completes a cart saved as completed', function() {
    $fixture = OrdersFixture::seed();
    $cart = orderEditCart($fixture);

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($cart, [
        'isCompleted' => true,
    ]))])->assertRedirect();

    $completed = freshOrder($cart);

    expect($completed->isCompleted)->toBeTrue();
    expect($completed->dateOrdered)->toBeInstanceOf(DateTime::class);
    expect($completed->orderStatusId)->not->toBeNull();
    expect($completed->getRecalculationMode())->toBe(Order::RECALCULATION_MODE_NONE);
});

it('replaces the order’s notices with the submitted ones', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'adminNotices' => [['type' => 'note', 'attribute' => 'lineItems', 'message' => 'Check stock']],
        'notices' => [['type' => 'note', 'attribute' => 'shippingAddress', 'message' => 'Address changed']],
    ]))])->assertRedirect();

    $saved = freshOrder($order);

    expect(collect($saved->getAdminNotices())->pluck('message')->all())->toBe(['Check stock']);
    expect(collect($saved->getNotices())->pluck('message')->all())->toBe(['Address changed']);
});

it('does not save a draft with errors', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'reference' => 'NEW-REF',
        'lineItems' => [[
            'id' => null,
            'type' => LineItemType::Purchasable->value,
            'purchasableId' => 999999,
            'lineItemStatusId' => null,
            'qty' => 1,
            'price' => '1',
            'promotionalPrice' => null,
            'adjustments' => [],
        ]],
    ]))])->assertStatus(400);

    expect(freshOrder($order)->reference)->toBe($order->reference);
    expect(freshOrder($order)->getLineItems())->toHaveCount(2);
});

it('keeps the date ordered submitted with a completed order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    postJson(Url::actionUrl('commerce/orders/save'), ['orderData' => json_encode(orderEditDraft($order, [
        'dateOrdered' => ['date' => '2026-01-15', 'time' => '09:30', 'timezone' => 'UTC'],
    ]))])->assertRedirect();

    $dateOrdered = freshOrder($order)->dateOrdered->setTimezone(new DateTimeZone('UTC'));

    expect($dateOrdered->format('Y-m-d H:i'))->toBe('2026-01-15 09:30');
});
