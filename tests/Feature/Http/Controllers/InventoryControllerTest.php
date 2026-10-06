<?php

declare(strict_types=1);

use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;
use Illuminate\Support\Facades\Gate;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function() {
    $this->withoutVite();
    actingAs(User::find()->admin(true)->one());
    prioritizeCommerceRoutes();
    app(Plugin::class)->edition = Plugin::EDITION_ENTERPRISE;

    $this->fixture = VariantQueryFixture::seed();
    $this->inventoryItem = $this->fixture->blueVariant->getInventoryItem();
    $this->location = app(InventoryLocations::class)->getAllInventoryLocations()->first();
    $this->levelsUri = 'commerce/inventory/levels/' . $this->location->handle;
});

function setInventoryLevelForController(int $inventoryItemId, int $inventoryLocationId, string $type, int $quantity): void
{
    $update = new UpdateInventoryLevel();
    $update->type = $type;
    $update->updateAction = InventoryUpdateQuantityType::SET;
    $update->inventoryItemId = $inventoryItemId;
    $update->inventoryLocationId = $inventoryLocationId;
    $update->quantity = $quantity;

    app(Inventory::class)->executeUpdateInventoryLevels(UpdateInventoryLevelCollection::make([$update]));
}

function inventoryLevelRows(array $params = []): array
{
    return postJson(Url::actionUrl('commerce/inventory/inventory-levels-table-data'), [
        'inventoryLocationId' => test()->location->id,
        ...$params,
    ])->assertOk()->json();
}

it('redirects the inventory screen to the first location', function() {
    get(Url::cpUrl('commerce/inventory'))->assertRedirect($this->location->getCpManageInventoryUrl());
});

it('renders the stock levels page for a location', function() {
    get(Url::cpUrl($this->levelsUri, ['inventoryItemId' => $this->inventoryItem->id]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', $this->location->getUiLabel() . ' Inventory')
            ->where('crumbs.0.label', 'Commerce')
            ->where('crumbs.1.label', 'Inventory')
            ->where('form.nodes.0.props.searchable', true)
            ->where('form.nodes.0.props.dataUrl', fn(string $url) => str_contains($url, 'inventory-levels-table-data')
                && str_contains($url, 'inventoryLocationId=' . $this->location->id)
                && str_contains($url, 'inventoryItemId=' . $this->inventoryItem->id))
            ->where('form.nodes.0.props.columns', fn($columns) => collect($columns)->pluck('key')->all() === [
                'purchasable', 'sku', 'reserved', 'damaged', 'safety', 'qualityControl', 'committed', 'available', 'onHand', 'incoming',
            ])
        );
});

it('returns not found for an unknown location', function() {
    get(Url::cpUrl('commerce/inventory/levels/nope'))->assertNotFound();
});

it('requires the manage inventory stock levels permission', function() {
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageInventoryStockLevels' ? false : null);

    get(Url::cpUrl($this->levelsUri))->assertForbidden();
    postJson(Url::actionUrl('commerce/inventory/inventory-levels-table-data'), [
        'inventoryLocationId' => $this->location->id,
    ])->assertForbidden();
});

it('returns the stock level rows with their actions', function() {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'available', 7);

    $response = inventoryLevelRows(['inventoryItemId' => $this->inventoryItem->id]);
    $row = $response['data'][0];

    expect($response['data'])->toHaveCount(1)
        ->and($response['pagination'])->toMatchArray(['total' => 1, 'current_page' => 1, 'last_page' => 1, 'from' => 1, 'to' => 1])
        ->and($row['id'])->toBe($this->inventoryItem->id)
        ->and($row['purchasable']['url'])->toBe($this->fixture->blueVariant->getCpEditUrl())
        ->and($row['sku'])->toBe([
            'label' => $this->fixture->blueVariant->getSku(),
            'url' => Url::cpUrl('commerce/inventory/item/' . $this->inventoryItem->id),
            'slideout' => true,
        ])
        ->and($row['available']['label'])->toBe('7')
        ->and(array_column($row['available']['items'], 'label'))->toBe(['Set Quantity', 'Adjust Quantity', 'Move Inventory'])
        ->and($row['available']['items'][0]['modalUrl'])->toEndWith('actions/commerce/inventory/prepare-update-levels-modal')
        ->and($row['available']['items'][0]['actionUrl'])->toEndWith('actions/commerce/inventory/update-levels')
        ->and($row['available']['items'][0]['params'])->toBe([
            'inventoryLocationId' => $this->location->id,
            'inventoryItemId' => $this->inventoryItem->id,
            'type' => 'available',
            'updateAction' => 'set',
        ])
        ->and($row['available']['items'][2]['modalUrl'])->toEndWith('actions/commerce/inventory/prepare-movement-modal')
        ->and($row['available']['items'][2]['actionUrl'])->toEndWith('actions/commerce/inventory/save-inventory-movement')
        ->and($row['available']['items'][2]['params'])->toBe([
            'inventoryLocationId' => $this->location->id,
            'inventoryItemId' => $this->inventoryItem->id,
            'type' => 'available',
        ])
        // Nothing to move out of an empty level.
        ->and(array_column($row['damaged']['items'], 'label'))->toBe(['Set Quantity', 'Adjust Quantity'])
        ->and($row['onHand']['label'])->toBe('7')
        ->and($row['committed'])->toBe(0)
        ->and($row['incoming'])->toBe(0);
});

it('paginates, searches and sorts the stock level rows', function() {
    setInventoryLevelForController($this->fixture->whiteVariant->getInventoryItem()->id, $this->location->id, 'available', 3);
    setInventoryLevelForController($this->fixture->blueVariant->getInventoryItem()->id, $this->location->id, 'available', 9);
    setInventoryLevelForController($this->fixture->hoodieVariant->getInventoryItem()->id, $this->location->id, 'available', 5);

    $all = inventoryLevelRows(['sort' => [['field' => 'available', 'direction' => 'desc']]]);
    $total = $all['pagination']['total'];

    expect($total)->toBeGreaterThanOrEqual(3)
        ->and(array_slice(array_map(fn($row) => (int)$row['available']['label'], $all['data']), 0, 3))->toBe([9, 5, 3]);

    $paged = inventoryLevelRows(['per_page' => 1, 'page' => 2, 'sort' => [['field' => 'available', 'direction' => 'desc']]]);

    expect($paged['data'])->toHaveCount(1)
        ->and($paged['data'][0]['available']['label'])->toBe('5')
        ->and($paged['pagination'])->toMatchArray(['total' => $total, 'per_page' => 1, 'current_page' => 2, 'last_page' => $total, 'from' => 2, 'to' => 2]);

    $searched = inventoryLevelRows(['search' => $this->fixture->hoodieVariant->getSku()]);

    expect($searched['data'])->toHaveCount(1)
        ->and($searched['data'][0]['id'])->toBe($this->fixture->hoodieVariant->getInventoryItem()->id);
});

it('returns the set and adjust quantity modal forms', function(string $updateAction, string $title, string $label, int $quantity) {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'available', 7);

    $response = getJson(Url::actionUrl('commerce/inventory/prepare-update-levels-modal', [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => $updateAction,
    ]))->assertOk()->json();

    $fields = collect($response['form']['nodes'])->where('component', 'craft:field')->values();

    expect($response['title'])->toBe($title)
        ->and($response['submitLabel'])->toBe('Update')
        ->and($fields[0]['props']['label'])->toBe($label)
        ->and($response['form']['values'])->toBe(['quantity' => $quantity, 'note' => ''])
        ->and(collect($response['form']['nodes'])->firstWhere('uid', 'current-level')['props']['html'])
        ->toContain('currently has 7 Available');
})->with([
    'set' => ['set', 'Set Available Quantity', 'Set to', 7],
    'adjust' => ['adjust', 'Adjust Available Quantity', 'Adjust by', 0],
]);

it('rejects the quantity modal for an invalid request', function(array $overrides) {
    getJson(Url::actionUrl('commerce/inventory/prepare-update-levels-modal', [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'set',
        ...$overrides,
    ]))->assertBadRequest();
})->with([
    'type that can’t be adjusted' => [['type' => 'committed']],
    'unknown action' => [['updateAction' => 'nope']],
]);

it('posts the quantity modal’s values and parameters to update a stock level', function() {
    // The modal posts its Form's values along with the menu item's parameters.
    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'quantity' => 3,
        'note' => 'Recount',
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'set',
    ])->assertOk()->assertJsonPath('message', 'Inventory updated.');

    expect(app(Inventory::class)->getInventoryLevel($this->inventoryItem, $this->location)->availableTotal)->toBe(3);
});

it('sets and adjusts a stock level', function() {
    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'set',
        'quantity' => 10,
    ])->assertOk()->assertJsonPath('updatedItems.0.availableTotal', 10);

    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'inventoryLocationId' => $this->location->id,
        'ids' => [$this->inventoryItem->id],
        'type' => 'available',
        'updateAction' => 'adjust',
        'quantity' => -4,
        'note' => 'Shrinkage',
    ])->assertOk();

    expect(app(Inventory::class)->getInventoryLevel($this->inventoryItem, $this->location)->availableTotal)->toBe(6);
});

it('rejects a note longer than the transaction note column', function() {
    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'set',
        'quantity' => 3,
        'note' => str_repeat('a', 256),
    ])->assertUnprocessable()->assertJsonValidationErrors(['note']);

    postJson(Url::actionUrl('commerce/inventory/save-inventory-movement'), [
        'inventoryMovement' => [
            'inventoryItemId' => $this->inventoryItem->id,
            'fromInventoryLocationId' => $this->location->id,
            'toInventoryLocationId' => $this->location->id,
            'fromInventoryTransactionType' => 'available',
            'toInventoryTransactionType' => 'damaged',
            'quantity' => 1,
            'note' => str_repeat('a', 256),
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors(['inventoryMovement.note']);

    expect(app(Inventory::class)->getInventoryLevel($this->inventoryItem, $this->location)->availableTotal)->toBe(0);
});

it('doesn’t record an adjustment of zero', function() {
    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'adjust',
        'quantity' => 0,
    ])->assertBadRequest()->assertJsonPath('message', 'No inventory changes made.');
});

it('rejects an invalid stock level update', function(array $overrides) {
    postJson(Url::actionUrl('commerce/inventory/update-levels'), [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
        'updateAction' => 'set',
        'quantity' => 1,
        ...$overrides,
    ])->assertBadRequest();
})->with([
    'unknown action' => [['updateAction' => 'nope']],
    'type that can’t be adjusted' => [['type' => 'committed']],
    'no items' => [['inventoryItemId' => null]],
]);

it('returns the move inventory modal form', function() {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'available', 7);

    $response = getJson(Url::actionUrl('commerce/inventory/prepare-movement-modal', [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'available',
    ]))->assertOk()->json();

    expect($response['title'])->toBe('Move Available Inventory')
        ->and($response['submitLabel'])->toBe('Move')
        ->and($response['form']['values']['inventoryMovement'])->toEqual([
            'inventoryItemId' => $this->inventoryItem->id,
            'fromInventoryLocationId' => $this->location->id,
            'toInventoryLocationId' => $this->location->id,
            'fromInventoryTransactionType' => 'available',
            'toInventoryTransactionType' => 'reserved',
            'quantity' => 1,
            'note' => '',
        ]);
});

it('rejects the move inventory modal for a type that can’t be moved', function() {
    getJson(Url::actionUrl('commerce/inventory/prepare-movement-modal', [
        'inventoryLocationId' => $this->location->id,
        'inventoryItemId' => $this->inventoryItem->id,
        'type' => 'committed',
    ]))->assertBadRequest();
});

it('moves inventory between types', function() {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'available', 7);

    postJson(Url::actionUrl('commerce/inventory/save-inventory-movement'), [
        'inventoryMovement' => [
            'inventoryItemId' => $this->inventoryItem->id,
            'fromInventoryLocationId' => $this->location->id,
            'toInventoryLocationId' => $this->location->id,
            'fromInventoryTransactionType' => 'available',
            'toInventoryTransactionType' => 'damaged',
            'quantity' => 2,
        ],
    ])->assertOk();

    $level = app(Inventory::class)->getInventoryLevel($this->inventoryItem, $this->location);

    expect($level->availableTotal)->toBe(5)
        ->and($level->damagedTotal)->toBe(2);
});

it('doesn’t move more inventory than a type that can’t go negative has', function() {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'damaged', 1);

    postJson(Url::actionUrl('commerce/inventory/save-inventory-movement'), [
        'inventoryMovement' => [
            'inventoryItemId' => $this->inventoryItem->id,
            'fromInventoryLocationId' => $this->location->id,
            'toInventoryLocationId' => $this->location->id,
            'fromInventoryTransactionType' => 'damaged',
            'toInventoryTransactionType' => 'available',
            'quantity' => 5,
        ],
    ])->assertBadRequest()->assertJsonStructure(['errors' => ['inventoryMovement.quantity']]);

    expect(app(Inventory::class)->getInventoryLevel($this->inventoryItem, $this->location)->damagedTotal)->toBe(1);
});

it('renders the unfulfilled orders page', function() {
    get(Url::cpUrl("$this->levelsUri/orders", ['inventoryItemId' => $this->inventoryItem->id]))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', '0 Unfulfilled Orders')
            ->where('form.nodes.0.props.rows', [])
        );
});

it('renders the inventory item page with its history', function() {
    setInventoryLevelForController($this->inventoryItem->id, $this->location->id, 'available', 7);

    get(Url::cpUrl('commerce/inventory/item/' . $this->inventoryItem->id))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', $this->fixture->blueVariant->getSku())
            ->where('submit.url', Url::actionUrl('commerce/inventory/item-save'))
            ->where('form.values.inventoryItemId', $this->inventoryItem->id)
            ->where('form.nodes.0.props.label', 'Details')
            ->where('form.nodes.1.props.label', 'History')
            ->where('form.nodes.1.children.1.props.rows.0.quantity', 7)
            ->where('form.nodes.1.children.1.props.rows.0.type', 'Available')
        );
});

it('renders the inventory item screen for a slideout', function() {
    get(Url::cpUrl('commerce/inventory/item/' . $this->inventoryItem->id), [
        'X-Inertia' => 'true',
        'X-Craft-Container-Id' => 'slideout-1',
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('component', 'Form')
        ->assertJsonPath('props.title', $this->fixture->blueVariant->getSku())
        ->assertJsonPath('props.form.values.inventoryItemId', $this->inventoryItem->id)
        ->assertJsonPath('props.submit.url', Url::actionUrl('commerce/inventory/item-save'));
});

it('saves an inventory item', function() {
    postJson(Url::actionUrl('commerce/inventory/item-save'), [
        'inventoryItemId' => $this->inventoryItem->id,
        'countryCodeOfOrigin' => 'AU',
        'administrativeAreaCodeOfOrigin' => 'WA',
        'harmonizedSystemCode' => '6109.10',
    ])->assertOk();

    $inventoryItem = app(Inventory::class)->getInventoryItemById($this->inventoryItem->id);

    expect($inventoryItem->countryCodeOfOrigin)->toBe('AU')
        ->and($inventoryItem->administrativeAreaCodeOfOrigin)->toBe('WA')
        ->and($inventoryItem->harmonizedSystemCode)->toBe('6109.10');
});

it('returns not found for an unknown inventory item', function() {
    get(Url::cpUrl('commerce/inventory/item/999999'))->assertNotFound();
});
