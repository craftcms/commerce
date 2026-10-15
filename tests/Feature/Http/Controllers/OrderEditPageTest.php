<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Str;
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
