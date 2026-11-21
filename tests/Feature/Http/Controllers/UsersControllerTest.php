<?php

declare(strict_types=1);

use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Tests\Support\OrdersFixture;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();

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

it('lists a customer\'s orders and carts in an element index per tab', function() {
    $customer = $this->fixture->customer;

    get("/admin/users/{$customer->id}/commerce")
        ->assertOk()
        ->assertInertia(function(AssertableInertia $page) {
            $nodes = $page->toArray()['props']['ui']['nodes'];
            $tabs = findUiNodes($nodes, 'craft:tab');

            expect(collect($tabs)->pluck('uid')->all())
                ->toBe(['completed-orders', 'active-carts', 'inactive-carts']);

            $indexes = findUiNodes($nodes, 'craft:element-index');
            expect($indexes)->toHaveCount(3);

            $completedIds = collect($indexes[0]['props']['initial']['data'])->pluck('id')->sort()->values()->all();
            $expectedIds = collect($this->fixture->orders)->pluck('id')->sort()->values()->all();

            expect($completedIds)->toBe($expectedIds)
                ->and($indexes[1]['props']['initial']['data'])->toBe([])
                ->and($indexes[2]['props']['initial']['data'])->toBe([]);
        });
});
