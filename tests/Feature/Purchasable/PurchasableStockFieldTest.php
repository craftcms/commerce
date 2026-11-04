<?php

declare(strict_types=1);

use CraftCms\Cms\FieldLayout\FieldLayoutElementContext;
use CraftCms\Cms\Support\Url;
use CraftCms\Cms\Ui\Enums\ControlMode;
use CraftCms\Cms\Ui\Nodes\MarkdownContent;
use CraftCms\Cms\Ui\Nodes\Table;
use CraftCms\Cms\Ui\UiContext;
use CraftCms\Cms\User\Elements\User;
use CraftCms\Commerce\Inventory\Collections\UpdateInventoryLevelCollection;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Inventory\InventoryLocations;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Purchasable\FieldLayoutElements\PurchasableStockField;
use CraftCms\Commerce\Tests\Support\VariantQueryFixture;
use Illuminate\Support\Facades\Gate;

use function Pest\Laravel\actingAs;

beforeEach(function() {
    actingAs(User::find()->admin(true)->one());

    $this->variant = VariantQueryFixture::seed()->blueVariant;
    $this->variant->inventoryTracked = true;
    $this->location = app(InventoryLocations::class)->getAllInventoryLocations()->first();

    $update = new UpdateInventoryLevel();
    $update->type = 'available';
    $update->updateAction = InventoryUpdateQuantityType::SET;
    $update->inventoryItemId = $this->variant->inventoryItemId;
    $update->inventoryLocationId = $this->location->id;
    $update->quantity = 4;
    app(Inventory::class)->executeUpdateInventoryLevels(UpdateInventoryLevelCollection::make([$update]));

    $this->field = new PurchasableStockField(['uid' => 'stock-field']);
});

function stockFieldChildren(PurchasableStockField $field, Variant $variant, ControlMode $mode = ControlMode::Editable): array
{
    return $field->uiNode(new FieldLayoutElementContext($variant, new UiContext(), $mode))->children();
}

it('shows the stock available at each location with a manage stock slideout', function() {
    $children = stockFieldChildren($this->field, $this->variant);

    /** @var Table $table */
    $table = collect($children)->first(fn($child) => $child instanceof Table);
    $props = $table->props();

    expect($table->uid())->toBe('stock-field-inventory-levels')
        ->and(array_column($props['columns'], 'key'))->toBe(['location', 'available'])
        ->and($props['rows'])->toContain(['location' => $this->location->getUiLabel(), 'available' => 4])
        ->and($props['createLabel'])->toBe('Manage stock')
        ->and($props['createUrl'])->toBe(Url::cpUrl('commerce/inventory/item/' . $this->variant->inventoryItemId))
        ->and($props['createSlideout'])->toBeTrue();

    $summary = collect($children)->first(fn($child) => $child instanceof MarkdownContent && $child->uid() === 'stock-field-inventory-total');

    expect($summary->props()['html'])->toContain('saleable across ' . count($props['rows']));
});

it('hides the manage stock action when the field is read-only', function() {
    $table = collect(stockFieldChildren($this->field, $this->variant, ControlMode::ReadOnly))
        ->first(fn($child) => $child instanceof Table);

    expect($table->props()['createUrl'])->toBeNull();
});

it('hides the manage stock action without the manage inventory stock levels permission', function() {
    Gate::before(fn($user, $ability) => $ability === 'commerce-manageInventoryStockLevels' ? false : null);

    $table = collect(stockFieldChildren($this->field, $this->variant))
        ->first(fn($child) => $child instanceof Table);

    expect($table->props()['createUrl'])->toBeNull();
});

it('doesn’t show inventory when it isn’t tracked', function() {
    $this->variant->inventoryTracked = false;

    expect(collect(stockFieldChildren($this->field, $this->variant))->contains(fn($child) => $child instanceof Table || $child instanceof MarkdownContent))
        ->toBeFalse();
});

it('asks for an unsaved variant to be saved before managing its inventory', function() {
    $field = new PurchasableStockField(['uid' => 'stock-field', 'defaultInventoryTracked' => true]);
    $variant = new Variant();

    $children = collect(stockFieldChildren($field, $variant));

    expect($children->contains(fn($child) => $child instanceof Table))->toBeFalse()
        ->and($children->first(fn($child) => $child instanceof MarkdownContent)->props()['html'])
        ->toContain('Inventory can be managed once this product variant is saved.');
});
