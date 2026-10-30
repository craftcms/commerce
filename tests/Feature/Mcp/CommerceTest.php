<?php

declare(strict_types=1);

use CraftCms\Cms\Config\GeneralConfig;
use CraftCms\Cms\Mcp\RouteRegistrar;
use CraftCms\Cms\Mcp\Settings;
use CraftCms\Cms\ProjectConfig\ProjectConfig;
use CraftCms\Cms\User\Models\User;
use CraftCms\Commerce\Management\Resources;
use CraftCms\Commerce\Management\ResourceType;
use CraftCms\Commerce\Store\Stores;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;

beforeEach(function(): void {
    // MCP is disabled by default, and its routes follow the MCP settings.
    app(ProjectConfig::class)->set('mcp', ['route' => '/mcp', 'authorizationRoute' => '/mcp/auth', 'enabled' => true]);
    app()->forgetInstance(Settings::class);
    $routes = new RouteCollection();
    foreach (Route::getRoutes()->getRoutes() as $route) {
        $routes->add($route);
    }
    Route::setRoutes($routes);
    app(UrlGenerator::class)->setRoutes($routes);
    app(RouteRegistrar::class)->register();

    $key = openssl_pkey_new(['private_key_bits' => 2048]);
    config()->set('passport.public_key', openssl_pkey_get_details($key)['key']);
    $this->actor = User::query()->firstOrFail();
    Passport::actingAs($this->actor, ['mcp:admin'], 'craft-mcp');
});

function commerceMcp(string $name, array $arguments = [], int $status = 200): TestResponse
{
    Route::getRoutes()->getByName('craft.mcp.server')->flushController();

    return test()->postJson(route('craft.mcp.server'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $name, 'arguments' => (object)$arguments, '_meta' => [
            'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
            'io.modelcontextprotocol/clientCapabilities' => (object)[],
        ]],
    ], ['MCP-Protocol-Version' => '2026-07-28', 'Mcp-Method' => 'tools/call', 'Mcp-Name' => $name])->assertStatus($status);
}

it('discovers Commerce resources and all element adapters', function(): void {
    commerceMcp('commerce.configuration.schema')->assertJsonPath('result.isError', false);
    commerceMcp('commerce.resources.schema')->assertJsonPath('result.isError', false);
    foreach (['products', 'variants', 'orders', 'transfers'] as $type) {
        commerceMcp('elements.schema', ['type' => $type])->assertJsonPath('result.structuredContent.type', $type);
    }
});

it('creates reads partially updates and deletes store-management records', function(): void {
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    $item = commerceMcp('commerce.resources.create', ['type' => 'shippingMethods', 'attributes' => ['name' => 'Express', 'handle' => 'express', 'storeId' => $storeId]])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.item');
    commerceMcp('commerce.resources.update', ['type' => 'shippingMethods', 'identifier' => ['handle' => 'express'], 'storeId' => $storeId, 'attributes' => ['name' => 'Next day']])
        ->assertJsonPath('result.structuredContent.item.name', 'Next day')
        ->assertJsonPath('result.structuredContent.item.handle', 'express');
    commerceMcp('commerce.resources.get', ['type' => 'shippingMethods', 'identifier' => ['id' => $item['id']], 'storeId' => $storeId])
        ->assertJsonPath('result.structuredContent.item.name', 'Next day');
    commerceMcp('commerce.resources.delete', ['type' => 'shippingMethods', 'identifier' => ['id' => $item['id']], 'storeId' => $storeId])
        ->assertJsonPath('result.structuredContent.deleted', true);
});

it('lists every resource type without exposing gateway settings', function(): void {
    $storeId = app(Stores::class)->getPrimaryStore()->id;
    foreach ([false, true] as $configuration) {
        $prefix = $configuration ? 'commerce.configuration' : 'commerce.resources';
        $types = commerceMcp("$prefix.schema")->json('result.structuredContent.types');
        foreach ($types as $type) {
            $response = commerceMcp("$prefix.list", ['type' => $type['type'], 'storeId' => $storeId])->assertJsonPath('result.isError', false);
            if ($type['type'] === 'gateways') {
                foreach ($response->json('result.structuredContent.items') as $gateway) {
                    expect($gateway)->not->toHaveKeys(['settings', 'password', 'apiKey']);
                }
            }
        }
    }
});

it('enforces administrative changes in the Commerce service without MCP', function(): void {
    app(GeneralConfig::class)->allowAdminChanges = false;
    expect(fn() => app(Resources::class)->save($this->actor, ResourceType::Stores, true, ['name' => 'Forbidden', 'handle' => 'forbidden']))
        ->toThrow(AuthorizationException::class);
});

it('does not let a read token call mutations or a write token call configuration', function(): void {
    Passport::actingAs($this->actor, ['mcp:read'], 'craft-mcp');
    commerceMcp('commerce.resources.create', ['type' => 'taxCategories', 'attributes' => ['name' => 'Forbidden', 'handle' => 'forbidden']], 400)
        ->assertJsonPath('error.code', -32602);
    Passport::actingAs($this->actor, ['mcp:write'], 'craft-mcp');
    commerceMcp('commerce.configuration.list', ['type' => 'stores'], 400)->assertJsonPath('error.code', -32602);
});

it('rejects undeclared attributes and ambiguous identifiers', function(): void {
    commerceMcp('commerce.resources.create', ['type' => 'taxCategories', 'attributes' => ['name' => 'Injected', 'handle' => 'injected', 'id' => 1]])
        ->assertJsonPath('result.isError', true);
    commerceMcp('commerce.resources.get', ['type' => 'taxCategories', 'identifier' => ['id' => 1, 'handle' => 'general']])
        ->assertJsonPath('result.isError', true);
});

it('manages products variants and carts through the shared element tools', function(): void {
    $fixture = \CraftCms\Commerce\Tests\Support\OrdersFixture::seed();
    $product = commerceMcp('elements.create', ['type' => 'products', 'attributes' => ['typeId' => $fixture->product->typeId, 'title' => 'MCP product', 'defaultVariant' => ['sku' => 'mcp-default', 'basePrice' => 12]]])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.element');
    $variant = commerceMcp('elements.create', ['type' => 'variants', 'attributes' => ['productId' => $product['id'], 'title' => 'Large', 'sku' => 'mcp-large', 'basePrice' => 17.25]])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.element');
    commerceMcp('elements.update', ['type' => 'variants', 'id' => $variant['id'], 'attributes' => ['basePrice' => 18.5]])
        ->assertJsonPath('result.structuredContent.element.basePrice', 18.5);
    $order = commerceMcp('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $fixture->storeId]])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.element');
    expect($order)->not->toHaveKeys(['number', 'returnUrl', 'cancelUrl', 'paymentSourceId']);
    commerceMcp('commerce.orders.set-line-item', ['orderId' => $order['id'], 'purchasableId' => $variant['id'], 'quantity' => 3])
        ->assertJsonPath('result.structuredContent.order.totalQty', 3);
    $items = commerceMcp('commerce.orders.related', ['orderId' => $order['id'], 'relation' => 'lineItems'])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.items');
    expect($items[0]['qty'])->toBe(3);
    expect((float)$items[0]['total'])->toBe(55.5);
    commerceMcp('commerce.orders.set-line-item', ['orderId' => $order['id'], 'lineItemId' => $items[0]['id'], 'quantity' => 0])
        ->assertJsonPath('result.structuredContent.order.totalQty', 0);
    foreach (['transactions', 'history', 'adjustments', 'fulfillment'] as $relation) {
        commerceMcp('commerce.orders.related', ['orderId' => $fixture->orders['completed-new']->id, 'relation' => $relation])->assertJsonPath('result.isError', false);
    }
    commerceMcp('commerce.orders.set-status', ['orderId' => $fixture->orders['completed-new']->id, 'statusId' => $fixture->shippedOrderStatusId])
        ->assertJsonPath('result.structuredContent.order.orderStatusId', $fixture->shippedOrderStatusId);
});

it('updates inventory and dispatches and receives transfers without overreceiving', function(): void {
    $fixture = \CraftCms\Commerce\Tests\Support\OrdersFixture::seed();
    $origin = app(\CraftCms\Commerce\Inventory\InventoryLocations::class)->getAllInventoryLocations()->first();
    $destination = commerceMcp('commerce.resources.create', ['type' => 'inventoryLocations', 'attributes' => ['name' => 'Second', 'handle' => 'second']])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.item.id');
    $itemId = $fixture->white->inventoryItemId;
    commerceMcp('commerce.inventory.update', ['locationId' => $origin->id, 'itemId' => $itemId, 'type' => 'available', 'action' => 'set', 'quantity' => 20])
        ->assertJsonPath('result.isError', false);
    commerceMcp('commerce.inventory.move', ['locationId' => $origin->id, 'itemId' => $itemId, 'from' => 'available', 'to' => 'reserved', 'quantity' => 2])
        ->assertJsonPath('result.isError', false);
    commerceMcp('commerce.inventory.history', ['locationId' => $origin->id, 'itemId' => $itemId])->assertJsonPath('result.isError', false);
    $transfer = commerceMcp('commerce.transfers.save', ['originLocationId' => $origin->id, 'destinationLocationId' => $destination, 'items' => [['inventoryItemId' => $itemId, 'quantity' => 5]]])
        ->assertJsonPath('result.isError', false)->json('result.structuredContent.transfer');
    commerceMcp('commerce.transfers.dispatch', ['id' => $transfer['id']])->assertJsonPath('result.structuredContent.transfer.transferStatus', 'pending');
    $details = commerceMcp('commerce.transfers.details', ['id' => $transfer['id']])->assertJsonPath('result.isError', false)->json('result.structuredContent.items');
    commerceMcp('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$details[0]['uid'] => ['accept' => 6]]])->assertJsonPath('result.isError', true);
    commerceMcp('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$details[0]['uid'] => ['accept' => 3, 'reject' => 2]]])
        ->assertJsonPath('result.structuredContent.transfer.transferStatus', 'received');
    commerceMcp('commerce.transfers.receive', ['id' => $transfer['id'], 'quantities' => [$details[0]['uid'] => ['accept' => 1]]])->assertJsonPath('result.isError', true);
    commerceMcp('commerce.inventory.list', ['locationId' => $destination, 'itemId' => $itemId])->assertJsonPath('result.isError', false);
});

it('replaces discount coupons and applies cart details and coupons', function(): void {
    $fixture = \CraftCms\Commerce\Tests\Support\OrdersFixture::seed();
    $discount = commerceMcp('commerce.resources.create', ['type' => 'discounts', 'attributes' => [
        'storeId' => $fixture->storeId, 'name' => 'Five off', 'enabled' => true, 'allPurchasables' => true, 'allCategories' => true,
        'requireCouponCode' => true, 'baseDiscount' => -5, 'coupons' => [['code' => 'FIVE'], ['code' => 'OLD', 'maxUses' => 2]],
    ]])->assertJsonPath('result.isError', false)->json('result.structuredContent.item');
    expect(collect($discount['coupons'])->pluck('code')->sort()->values()->all())->toBe(['FIVE', 'OLD']);
    $five = collect($discount['coupons'])->firstWhere('code', 'FIVE');
    commerceMcp('commerce.resources.update', ['type' => 'discounts', 'identifier' => ['id' => $discount['id']], 'storeId' => $fixture->storeId, 'attributes' => ['coupons' => [['id' => $five['id'], 'code' => 'FIVE', 'maxUses' => 10]]]])
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.item.coupons.0.id', $five['id'])
        ->assertJsonPath('result.structuredContent.item.coupons.0.maxUses', 10)
        ->assertJsonCount(1, 'result.structuredContent.item.coupons');

    $order = commerceMcp('elements.create', ['type' => 'orders', 'attributes' => ['storeId' => $fixture->storeId]])->json('result.structuredContent.element');
    commerceMcp('commerce.orders.set-line-item', ['orderId' => $order['id'], 'purchasableId' => $fixture->white->id, 'quantity' => 2])->assertJsonPath('result.isError', false);
    commerceMcp('elements.update', ['type' => 'orders', 'id' => $order['id'], 'attributes' => [
        'email' => 'mcp-buyer@crafttest.com',
        'couponCode' => 'FIVE',
        'shippingAddress' => ['firstName' => 'Ada', 'lastName' => 'Lovelace', 'countryCode' => 'US', 'administrativeArea' => 'NY', 'locality' => 'New York', 'postalCode' => '10001', 'addressLine1' => '1 Main St'],
    ]])->assertJsonPath('result.isError', false);

    $saved = \CraftCms\Commerce\Order\Elements\Order::find()->id($order['id'])->status(null)->one();
    expect($saved->getEmail())->toBe('mcp-buyer@crafttest.com')
        ->and($saved->couponCode)->toBe('FIVE')
        ->and($saved->getShippingAddress()?->addressLine1)->toBe('1 Main St')
        ->and($saved->getShippingAddress()?->countryCode)->toBe('US')
        ->and((float)$saved->getTotalDiscount())->toBe(-5.0)
        ->and((float)$saved->getTotal())->toBe(round(19.99 * 2 - 5, 2));
});

it('reads and updates store settings, topology and reports', function(): void {
    $fixture = \CraftCms\Commerce\Tests\Support\OrdersFixture::seed();
    commerceMcp('commerce.stores.update-settings', ['storeId' => $fixture->storeId, 'attributes' => ['countries' => ['AU', 'US']]])
        ->assertJsonPath('result.isError', false);
    commerceMcp('commerce.stores.settings', ['storeId' => $fixture->storeId])
        ->assertJsonPath('result.structuredContent.countries', ['AU', 'US']);
    commerceMcp('commerce.stores.update-settings', ['storeId' => $fixture->storeId, 'attributes' => ['countries' => ['usa']]])
        ->assertJsonPath('result.isError', true);
    commerceMcp('commerce.stores.topology', ['storeId' => $fixture->storeId])
        ->assertJsonPath('result.structuredContent.siteIds', [\CraftCms\Cms\Support\Facades\Sites::getPrimarySite()->id]);

    $completed = \CraftCms\Commerce\Order\Elements\Order::find()->isCompleted()->storeId($fixture->storeId)->count();
    expect($completed)->toBeGreaterThan(0);
    commerceMcp('commerce.reports.get', ['storeId' => $fixture->storeId, 'type' => 'totalOrders', 'dateRange' => 'all'])
        ->assertJsonPath('result.isError', false)
        ->assertJsonPath('result.structuredContent.data.total', $completed);
    foreach (array_keys(\CraftCms\Commerce\Stats\Reports::TYPES) as $type) {
        commerceMcp('commerce.reports.get', ['storeId' => $fixture->storeId, 'type' => $type, 'dateRange' => 'all'])->assertJsonPath('result.isError', false);
    }
});

it('denies order changes and customer reports in Commerce for users without those permissions', function(): void {
    $fixture = \CraftCms\Commerce\Tests\Support\OrdersFixture::seed();
    $permissions = ['accessCp', 'useCraftMcp', 'accessPlugin-commerce', 'commerce-manageOrders'];
    $mock = Mockery::mock(\CraftCms\Cms\User\UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')->andReturnUsing(fn(int $userId, string $permission): bool => in_array($permission, $permissions, true));
    app()->instance(\CraftCms\Cms\User\UserPermissions::class, $mock);
    $user = User::query()->findOrFail($fixture->customer->id);
    $order = $fixture->orders['completed-new'];

    expect(app(\CraftCms\Commerce\Order\OrderManagement::class)->find($user, $order->id)->id)->toBe($order->id)
        ->and(fn() => app(\CraftCms\Commerce\Order\OrderManagement::class)->status($user, $order->id, $fixture->shippedOrderStatusId))->toThrow(InvalidArgumentException::class)
        ->and(app(\CraftCms\Commerce\Stats\Reports::class)->get($user, $fixture->storeId, 'totalOrders')['type'])->toBe('totalOrders')
        ->and(fn() => app(\CraftCms\Commerce\Stats\Reports::class)->get($user, $fixture->storeId, 'topCustomers'))->toThrow(AuthorizationException::class)
        ->and(fn() => app(Resources::class)->list($user, ResourceType::Discounts, false, $fixture->storeId))->toThrow(AuthorizationException::class);

    Passport::actingAs($user, ['mcp:admin'], 'craft-mcp');
    commerceMcp('elements.get', ['type' => 'orders', 'id' => $order->id])->assertJsonPath('result.isError', false);
    expect(\CraftCms\Commerce\Order\Elements\Order::find()->id($order->id)->status(null)->one()->orderStatusId)->toBe($order->orderStatusId);
});
