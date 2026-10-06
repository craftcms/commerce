<?php

declare(strict_types=1);

use CraftCms\Cms\Address\Elements\Address;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Database\Table;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\InventoryLocation;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Plugin;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;
use Illuminate\Support\Facades\DB;
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
});

function createInventoryLocationForController(string $handle): InventoryLocation
{
    $address = new Address();
    $address->countryCode = 'US';
    $address->title = $handle;
    Elements::saveElement($address);

    $inventoryLocation = new InventoryLocation();
    $inventoryLocation->name = ucfirst($handle);
    $inventoryLocation->handle = $handle;
    $inventoryLocation->setAddress($address);

    if (!app(InventoryLocations::class)->saveInventoryLocation($inventoryLocation)) {
        throw new RuntimeException('Could not save inventory location: ' . json_encode($inventoryLocation->errors()->all()));
    }

    return $inventoryLocation;
}

it('renders the inventory locations index page', function() {
    $inventoryLocation = createInventoryLocationForController('warehouse');

    get(Url::cpUrl('commerce/inventory-locations'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', 'Inventory Locations')
            ->where('crumbs.0.label', 'Commerce')
            ->where('form.nodes.0.props.rows', fn($rows) => collect($rows)->contains(
                fn(array $row) => $row['id'] === $inventoryLocation->id && $row['handle'] === 'warehouse'
            ))
        );
});

it('requires the manage inventory locations permission', function() {
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageInventoryLocations' ? false : null);

    get(Url::cpUrl('commerce/inventory-locations'))->assertForbidden();
});

it('renders the new inventory location page', function() {
    get(Url::cpUrl('commerce/inventory-locations/new'))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', 'Create a new inventory location')
            ->where('submit.url', Url::actionUrl('commerce/inventory-locations/save'))
            ->has('form.nodes')
        );
});

it('renders the inventory location edit page', function() {
    $inventoryLocation = createInventoryLocationForController('warehouse');

    get(Url::cpUrl("commerce/inventory-locations/$inventoryLocation->id"))
        ->assertOk()
        ->assertInertia(fn(AssertableInertia $page) => $page
            ->component('Form', false)
            ->where('title', 'Warehouse')
            ->where('form.values.inventoryLocationId', $inventoryLocation->id)
            ->where('form.values.handle', 'warehouse')
        );
});

it('creates an inventory location with its address', function() {
    postJson(Url::actionUrl('commerce/inventory-locations/save'), [
        'name' => 'Warehouse',
        'handle' => 'warehouse',
        'countryCode' => 'FR',
        'address' => [
            'addressLine1' => '12 Rue de Rivoli',
            'locality' => 'Paris',
            'postalCode' => '75001',
        ],
    ])->assertOk();

    $inventoryLocation = app(InventoryLocations::class)->getInventoryLocationByHandle('warehouse');
    $address = Elements::getElementById($inventoryLocation->addressId, Address::class);

    expect($address->title)->toBe('Warehouse')
        ->and($address->countryCode)->toBe('FR')
        ->and($address->addressLine1)->toBe('12 Rue de Rivoli')
        ->and($address->locality)->toBe('Paris')
        ->and($address->postalCode)->toBe('75001');
});

it('updates an existing inventory location and keeps its address', function() {
    $inventoryLocation = createInventoryLocationForController('warehouse');

    postJson(Url::actionUrl('commerce/inventory-locations/save'), [
        'inventoryLocationId' => $inventoryLocation->id,
        'addressId' => $inventoryLocation->addressId,
        'name' => 'Main Warehouse',
        'handle' => 'warehouse',
        'countryCode' => 'US',
        'address' => ['addressLine1' => '1 Main St'],
    ])->assertOk();

    $saved = app(InventoryLocations::class)->getInventoryLocationById($inventoryLocation->id);

    expect($saved->name)->toBe('Main Warehouse')
        ->and($saved->addressId)->toBe($inventoryLocation->addressId)
        ->and($saved->getAddress()->addressLine1)->toBe('1 Main St');
});

it('does not save an address when the inventory location is invalid', function() {
    $existing = createInventoryLocationForController('warehouse');
    $addressCount = DB::table('addresses')->count();

    postJson(Url::actionUrl('commerce/inventory-locations/save'), [
        'name' => $existing->name,
        'handle' => $existing->handle,
        'countryCode' => 'US',
        'address' => ['addressLine1' => '1 Orphan St'],
    ])
        ->assertStatus(400)
        ->assertJsonStructure(['errors' => ['name', 'handle']]);

    expect(DB::table('addresses')->count())->toBe($addressCount);
});

it('rejects a new inventory location beyond the Pro edition limit', function() {
    app(Plugin::class)->edition = Plugin::EDITION_PRO;

    $existingCount = app(InventoryLocations::class)->getAllInventoryLocations()->count();
    for ($i = $existingCount; $i < Plugin::EDITION_PRO_STORE_LIMIT; $i++) {
        createInventoryLocationForController("location$i");
    }

    postJson(Url::actionUrl('commerce/inventory-locations/save'), [
        'name' => 'One Too Many',
        'handle' => 'oneTooMany',
        'countryCode' => 'US',
    ])->assertStatus(400);

    expect(app(InventoryLocations::class)->getInventoryLocationByHandle('oneTooMany'))->toBeNull();
});

it('returns the delete modal form without the location being deleted', function() {
    $inventoryLocation = createInventoryLocationForController('warehouse');

    $response = getJson(Url::actionUrl('commerce/inventory-locations/prepare-delete-modal', ['id' => $inventoryLocation->id]))
        ->assertOk();

    $options = collect($response->json('form.nodes.0.control.props.options'))->pluck('value');

    expect($options)->not->toContain((string)$inventoryLocation->id)
        ->and($options)->not->toBeEmpty()
        ->and($response->json('form.values.destinationInventoryLocation'))->toBe($options->first());
});

it('moves stock to the destination when deleting an inventory location', function() {
    $fixture = VariantQueryFixture::seed();
    $inventoryItem = $fixture->blueVariant->getInventoryItem();
    $source = createInventoryLocationForController('source');
    $destination = createInventoryLocationForController('destination');

    $update = new UpdateInventoryLevel();
    $update->type = 'available';
    $update->updateAction = InventoryUpdateQuantityType::SET;
    $update->inventoryItemId = $inventoryItem->id;
    $update->inventoryLocationId = $source->id;
    $update->quantity = 7;
    app(Inventory::class)->executeUpdateInventoryLevels(UpdateInventoryLevelCollection::make([$update]));

    postJson(Url::actionUrl('commerce/inventory-locations/deactivate'), [
        'id' => $source->id,
        'destinationInventoryLocation' => $destination->id,
    ])->assertOk();

    expect(app(Inventory::class)->getInventoryLevel($inventoryItem, $destination)?->availableTotal)->toBe(7)
        ->and(DB::table(Table::INVENTORYLOCATIONS)->where('id', $source->id)->value('dateDeleted'))->not->toBeNull();
});

it('returns not found when deleting an unknown inventory location', function() {
    $destination = createInventoryLocationForController('destination');

    postJson(Url::actionUrl('commerce/inventory-locations/deactivate'), [
        'id' => 999999,
        'destinationInventoryLocation' => $destination->id,
    ])->assertNotFound();
});
