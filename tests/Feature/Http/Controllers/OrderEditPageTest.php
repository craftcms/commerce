<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Order\Edit\Operations\AddCustomLineItem;
use CraftCms\Commerce\Order\Edit\OrderEdit;
use CraftCms\Commerce\Order\Edit\OrderOperations;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\ShippingCategories;
use CraftCms\Commerce\Tax\TaxCategories;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
});

function orderEditPagePath(int $orderId): string
{
    return '/' . Cms::config()->cpTrigger . "/commerce/orders/$orderId";
}

it('renders a completed order', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $lineItem = $order->getLineItems()[0];

    get(orderEditPagePath($order->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::orders/Edit', false)
            ->where('orderId', $order->id)
            ->where('isCompleted', true)
            ->where('title', "Order {$order->reference}")
            ->has('statusHtml', 2)
            ->has('lineItems', count($order->getLineItems()))
            ->where('lineItems.0.uid', $lineItem->uid)
            ->where('lineItems.0.qty', $lineItem->qty)
            ->where('totalPrice', $order->totalPriceAsCurrency)
            ->where('payments.outstanding', $order->outstandingBalanceAsCurrency)
            ->has('histories')
            ->has('transactions')
            ->where('crumbs.1.label', 'Orders')
            ->etc()
        );
});

it('renders a cart without payment details', function() {
    $fixture = OrdersFixture::seed();
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    get(orderEditPagePath($cart->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::orders/Edit', false)
            ->where('isCompleted', false)
            ->where('payments', null)
            ->has('lineItems', 0)
            ->etc()
        );
});

it('renders a custom line item without a purchasable link', function() {
    $fixture = OrdersFixture::seed();
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);
    $cart = Order::find()->id($cart->id)->one();

    app(OrderOperations::class)->commit($cart, new OrderEdit([
        new AddCustomLineItem((string)Str::uuid(), 'Gift wrap', 'WRAP', 4.5, app(TaxCategories::class)->getDefaultTaxCategory()->id, app(ShippingCategories::class)->getDefaultShippingCategory($fixture->storeId)->id),
    ]));

    get(orderEditPagePath($cart->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('lineItems.0.description', 'Gift wrap')
            ->where('lineItems.0.url', null)
            ->etc()
        );
});

it('offers a reference form on carts', function() {
    $fixture = OrdersFixture::seed();
    $cart = new Order();
    $cart->number = bin2hex(random_bytes(16));
    $cart->storeId = $fixture->storeId;
    Elements::saveElement($cart, false);

    get(orderEditPagePath($cart->id))
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('details', fn($details) => collect($details)->contains(fn($row) => $row['label'] === 'Reference' && $row['form'] === 'setReference'))
            ->etc()
        );
});

it('offers only the sites of the order’s store as order sites', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $storeSiteIds = $order->getStore()->getSites()->map(fn($site) => (string)$site->id)->values()->all();

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('orderSiteId', (string)$order->orderSiteId)
            ->where('orderSiteOptions', fn($options) => collect($options)->pluck('value')->all() === $storeSiteIds)
            ->etc()
        );
});

it('shows an order as its edit session would leave it', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $uid = $order->getLineItems()[0]->uid;
    $originalQty = $order->getLineItems()[0]->qty;

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page->where('session', null)->etc());

    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'updateLineItem', 'lineItemUid' => $uid, 'changes' => ['qty' => 9]],
    ])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'setCouponCode', 'couponCode' => 'SESSION'],
    ])->assertOk();

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('session.mine', true)
            ->where('session.operations.0.label', 'Update Line Item')
            ->where('session.operations.0.summary', fn($summary) => str_ends_with($summary, 'Quantity: ' . $originalQty . ' → 9'))
            ->where('session.operations.1.label', 'Change Coupon Code')
            ->where('session.operations.1.summary', fn($summary) => str_ends_with($summary, '→ SESSION'))
            ->where('session.stale', false)
            ->where('session.totals', fn($totals) => collect($totals)->pluck('label')->take(2)->all() === ['Original total', 'New total']
                && collect($totals)->every(fn($row) => isset($row['amount'], $row['emphasis'], $row['divider'])))
            ->where('couponCode', 'SESSION')
            ->where('lineItems.0.qty', 9)
            ->where('lineItems.0.change', 'changed')
            ->where('changes', ['couponCode'])
            ->etc()
        );

    expect(Order::find()->id($order->id)->one()->couponCode)->not->toBe('SESSION');
});

it('shows the line items a session removes', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $uid = $order->getLineItems()[0]->uid;
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();
    postJson(Url::actionUrl('commerce/orders/edit/add'), [
        'orderId' => $order->id,
        'operation' => ['type' => 'removeLineItem', 'lineItemUid' => $uid],
    ])->assertOk();

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('lineItems', fn($items) => collect($items)->contains(fn($item) => $item['uid'] === $uid && $item['change'] === 'removed'))
            ->etc()
        );
});

it('tells another user that an order is being edited', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    postJson(Url::actionUrl('commerce/orders/edit/start'), ['orderId' => $order->id])->assertOk();

    DB::table(Table::ORDER_EDIT_SESSIONS)->where('orderId', $order->id)->update(['userId' => null]);

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('session.mine', false)
            ->where('session.operations', [])
            ->etc()
        );
});

it('renders status history messages as Markdown, with HTML escaped', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    $history = $order->getHistories()[0];
    DB::table(Table::ORDERHISTORIES)
        ->where('id', $history->id)
        ->update(['message' => "**Shipped** <script>alert(1)</script>"]);

    get(orderEditPagePath($order->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('histories.0.messageHtml', fn(string $html) => str_contains($html, '<strong>Shipped</strong>') && !str_contains($html, '<script>'))
            ->etc()
        );
});

it('returns a 404 for a missing order', function() {
    get(orderEditPagePath(999999))->assertNotFound();
});

it('needs permission to manage orders', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageOrders' ? false : null);

    get(orderEditPagePath($order->id))->assertForbidden();
});

it('only offers editing to users who can edit orders', function() {
    $order = OrdersFixture::seed()->orders['completed-new'];

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page->where('canEdit', true)->etc());

    Gate::before(fn($user, $ability) => $ability === 'commerce-editOrders' ? false : null);

    get(orderEditPagePath($order->id))
        ->assertInertia(fn(AssertableInertia $page) => $page->where('canEdit', false)->etc());
});
