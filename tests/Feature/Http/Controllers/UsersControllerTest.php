<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Cms\User\UserPermissions;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use CraftCms\Commerce\Tests\Support\StoresFixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

    $this->stores = StoresFixture::seed();
    $this->fixture = OrdersFixture::seed();
});

/**
 * @param array<int, mixed>|Collection<int, mixed> $nodes
 * @return list<array<string, mixed>>
 */
function findUiNodes(array|Collection $nodes, string $component): array
{
    $found = [];

    foreach ($nodes as $node) {
        if (data_get($node, 'component') === $component) {
            $found[] = $node;
        }

        $found = [...$found, ...findUiNodes(data_get($node, 'children', []), $component)];
    }

    return $found;
}

/**
 * @param array<string, mixed> $node
 * @return list<int>
 */
function listedOrderIds(array $node): array
{
    return collect($node['props']['initial']['data'])->pluck('id')->sort()->values()->all();
}

it('lists a customer\'s orders and carts in a tab per store', function() {
    $orders = array_values($this->fixture->orders);
    DB::table(Table::ORDERS)->where('id', $orders[0]->id)->update(['storeId' => $this->stores->ukStore->id]);

    get("/admin/users/{$this->fixture->customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) use ($orders) {
            $nodes = $page->toArray()['props']['ui']['nodes'];
            $primary = $this->stores->primaryStore->handle;

            expect(collect(findUiNodes($nodes, 'craft:tab'))->pluck('uid')->all())
                ->toBe([$primary, 'euStore', 'ukStore']);

            $indexes = collect(findUiNodes($nodes, 'craft:element-index'))->keyBy('uid');

            expect($indexes->keys()->all())->toBe([
                "$primary-completed-orders-index",
                "$primary-active-carts-index",
                "$primary-inactive-carts-index",
                'euStore-completed-orders-index',
                'euStore-active-carts-index',
                'euStore-inactive-carts-index',
                'ukStore-completed-orders-index',
                'ukStore-active-carts-index',
                'ukStore-inactive-carts-index',
            ])
                ->and(listedOrderIds($indexes["$primary-completed-orders-index"]))
                ->toBe(collect([$orders[1]->id, $orders[2]->id])->sort()->values()->all())
                ->and(listedOrderIds($indexes['ukStore-completed-orders-index']))->toBe([$orders[0]->id])
                ->and(listedOrderIds($indexes['euStore-completed-orders-index']))->toBe([])
                ->and(listedOrderIds($indexes["$primary-active-carts-index"]))->toBe([]);
        });
});

it('only shows the stores whose sites the viewer can edit', function() {
    $viewer = new User();
    $viewer->username = 'viewer';
    $viewer->email = 'viewer@crafttest.com';
    $viewer->active = true;
    Elements::saveElement($viewer);

    $permissions = ['accessCp', 'viewUsers', 'accessPlugin-commerce', 'commerce-manageOrders', 'editSite:' . $this->stores->ukSite->uid];
    $mock = Mockery::mock(UserPermissions::class)->makePartial();
    $mock->shouldReceive('doesUserHavePermission')
        ->andReturnUsing(fn(int $userId, string $permission): bool => in_array($permission, $permissions, true));
    app()->instance(UserPermissions::class, $mock);

    actingAs($viewer);

    get("/admin/users/{$this->fixture->customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) {
            $nodes = $page->toArray()['props']['ui']['nodes'];

            expect(collect(findUiNodes($nodes, 'craft:tab'))->pluck('uid')->all())->toBe(['ukStore']);
        });
});
