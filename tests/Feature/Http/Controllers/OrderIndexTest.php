<?php

declare(strict_types=1);

use CraftCms\Cms\Cms;
use CraftCms\Cms\Element\Actions\Delete;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Http\ViewModels\OrderIndexViewModel;
use CraftCms\Commerce\Order\Actions\CopyLoadCartUrl;
use CraftCms\Commerce\Order\Actions\UpdateOrderStatus;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Store\Stores;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

/**
 * @return array<string, array<string, mixed>>
 */
function orderIndexTestActions(AssertableInertia $page): array
{
    return array_column($page->toArray()['props']['actions'] ?? [], null, 'key');
}

/**
 * @return list<int>
 */
function orderIndexTestRowIds(AssertableInertia $page): array
{
    return array_map(intval(...), array_column($page->toArray()['props']['data'] ?? [], 'id'));
}

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->fixture = OrdersFixture::seed();
    $this->storeHandle = app(Stores::class)->getCurrentStore()->handle;
    $this->indexPath = '/' . Cms::config()->cpTrigger . '/commerce/orders';
});

it('renders the orders index page', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('commerce::orders/Index', false)
            ->where('elementType', Order::class)
            ->where('indexUrl', Url::cpUrl('commerce/orders'))
            ->where('orderStatusHandle', '')
            ->where('source.key', '*')
            ->where('crumbs.0.label', 'Commerce')
            ->where('sourceNavItems.0.label', 'All Orders')
            ->where('sourceNavItems.0.href', Url::cpUrl('commerce/orders'))
            ->where('sourceNavItems.1.group', true)
            ->where('sourceNavItems.1.subnav.0.href', Url::cpUrl('commerce/orders/new'))
            ->where('newOrderUrl', Url::cpUrl("commerce/orders/$this->storeHandle/create"))
            ->where('newOrderLabel', 'New order')
            ->where('dateRange', ['type' => null, 'from' => null, 'to' => null])
            ->has('dateRangeOptions', 8)
            ->where('orderStatuses.1.handle', 'shipped')
            ->has('data', 3)
        );
});

it('links cart sources by their handle', function() {
    get($this->indexPath)
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(collect($page->toArray()['props']['sourceNavItems'][1]['subnav'])->pluck('href'))
            ->toContain(
                Url::cpUrl('commerce/orders/cartsActive'),
                Url::cpUrl('commerce/orders/cartsInactive'),
                Url::cpUrl('commerce/orders/cartsAttemptedPayment'),
            ));
});

it('selects an order status source from the handle segment', function() {
    get($this->indexPath . '/shipped')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('orderStatusHandle', 'shipped')
            ->where('source.key', 'orderStatus:shipped')
            ->where('data.0.id', $this->fixture->orders['completed-shipped']->id)
            ->has('data', 1)
        );
});

it('selects a cart source from the handle segment', function() {
    get($this->indexPath . '/cartsActive')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('source.key', "carts:active:$this->storeHandle")
        );
});

it('falls back to all orders for an unknown handle', function() {
    get($this->indexPath . '/nope')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page->where('source.key', '*'));
});

it('limits orders to a date range preset', function() {
    get($this->indexPath . '?dateRange=today')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(orderIndexTestRowIds($page))
            ->toHaveCount(2)
            ->not->toContain($this->fixture->orders['completed-new-past']->id)
            ->and($page->toArray()['props']['dateRange'])->toBe(['type' => 'today', 'from' => null, 'to' => null]));
});

it('limits orders to a custom date range including its end date', function() {
    $yesterday = $this->fixture->orders['completed-new-past']->dateOrdered->format('Y-m-d');

    get($this->indexPath . "?dateRange=range&dateFrom=$yesterday&dateTo=$yesterday")
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(orderIndexTestRowIds($page))
            ->toBe([$this->fixture->orders['completed-new-past']->id]));
});

it('keeps the date range when switching sources', function() {
    get($this->indexPath . '?dateRange=today')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('sourceNavItems.1.subnav.0.href', Url::cpUrl('commerce/orders/new', ['dateRange' => 'today']))
        );
});

it('ignores an unknown date range', function() {
    get($this->indexPath . '?dateRange=before&dateFrom=nope')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('dateRange', ['type' => null, 'from' => null, 'to' => null])
            ->has('data', 3)
        );
});

it('requires the manage orders permission', function() {
    Gate::before(fn($user, string $ability) => $ability === 'commerce-manageOrders' ? false : null);

    get($this->indexPath)->assertForbidden();
});

it('leaves out creating orders and changing their status without the edit orders permission', function() {
    Gate::before(fn($user, string $ability) => $ability === 'commerce-editOrders' ? false : null);

    get($this->indexPath . '/shipped')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->where('newOrderUrl', null)
            ->where('orderStatuses', null)
            ->where('actions', fn($actions) => !collect($actions)->contains('key', UpdateOrderStatus::class))
        );
});

it('hands the status update action to the page and scopes action requests to the site', function() {
    get($this->indexPath . '/shipped')
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) {
            $actions = orderIndexTestActions($page);
            $updateStatus = $actions[UpdateOrderStatus::class]['action'];

            expect($updateStatus['type'])->toBe('event')
                ->and($updateStatus['name'])->toBe(OrderIndexViewModel::UPDATE_ORDER_STATUS_EVENT)
                ->and($updateStatus['detail']['url'])->toContain('site=')
                ->and($updateStatus['detail']['body'])->toMatchArray([
                    'elementAction' => UpdateOrderStatus::class,
                    'source' => 'orderStatus:shipped',
                    'context' => 'index',
                ])
                ->and($actions[Delete::class]['action']['url'])->toContain('site=');
        });
});

it('updates order statuses through the event’s request', function() {
    $order = $this->fixture->orders['completed-shipped'];
    $newStatusId = $this->fixture->orders['completed-new']->orderStatusId;
    $detail = null;

    get($this->indexPath . '/shipped')
        ->assertInertia(function(AssertableInertia $page) use (&$detail) {
            $detail = orderIndexTestActions($page)[UpdateOrderStatus::class]['action']['detail'];
        });

    postJson($detail['url'], [
        ...$detail['body'],
        'elementType' => Order::class,
        'elementIds' => [$order->id],
        'orderStatusId' => $newStatusId,
        'message' => 'Back to new',
        'suppressEmails' => true,
    ])->assertOk();

    expect(Order::find()->id($order->id)->one()->orderStatusId)->toBe($newStatusId);
});

it('offers sharing a cart for one cart at a time', function() {
    get($this->indexPath . '/cartsActive')
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => expect(orderIndexTestActions($page)[CopyLoadCartUrl::class])
            ->toMatchArray(['bulk' => false])
            ->and(orderIndexTestActions($page)[CopyLoadCartUrl::class]['action']['name'])
            ->toBe(OrderIndexViewModel::COPY_LOAD_CART_URL_EVENT));
});

it('returns a cart’s load URL by its ID', function() {
    $cart = new Order();
    $cart->number = 'cart-load-url-test';
    $cart->storeId = app(Stores::class)->getCurrentStore()->id;
    expect(\CraftCms\Cms\Support\Facades\Elements::saveElement($cart, false))->toBeTrue();

    get(Url::actionUrl('commerce/orders/get-load-cart-url', ['id' => $cart->id]), ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonStructure(['url']);
});

it('requires a cart number or ID for the load URL', function() {
    get(Url::actionUrl('commerce/orders/get-load-cart-url'), ['Accept' => 'application/json'])
        ->assertStatus(400)
        ->assertJsonPath('message', 'Missing number or id');
});

it('builds order status and cart source URLs from the handle', function() {
    expect(Order::sourceCpUri(['key' => 'orderStatus:shipped', 'data' => ['handle' => 'shipped']]))
        ->toBe('commerce/orders/shipped')
        ->and(Order::sourceCpUri(['key' => 'carts:active:primary', 'data' => ['handle' => 'cartsActive']]))
        ->toBe('commerce/orders/cartsActive')
        ->and(Order::sourceCpUri(['key' => 'custom:abc', 'data' => ['handle' => 'shipped']]))->toBeNull()
        ->and(Order::sourceCpUri(['key' => '*']))->toBeNull();
});
