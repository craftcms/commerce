<?php

declare(strict_types=1);

use CraftCms\Commerce\Inventory\Enums\InventoryUpdateQuantityType;
use CraftCms\Commerce\Inventory\Inventory;
use CraftCms\Commerce\Tests\Support\ProductConditionsFixture;

test('the inventory service resolves from the container', function() {
    expect(app(Inventory::class))->toBeInstanceOf(Inventory::class);
});

test('updatePurchasableInventoryLevel sets and adjusts a purchasable\'s stock', function(array $updateConfigs, int $expected) {
    $variant = ProductConditionsFixture::seed()->hoodieVariant;
    $variant->inventoryTracked = true;

    foreach ($updateConfigs as $updateConfig) {
        $qty = $updateConfig['quantity'];
        unset($updateConfig['quantity']);

        app(Inventory::class)->updatePurchasableInventoryLevel($variant, $qty, $updateConfig);
    }

    expect($variant->getStock())->toBe($expected);
})->with([
    'simple-single-arg' => [
        [
            ['quantity' => 10],
        ],
        10,
    ],
    'set-and-adjust' => [
        [
            ['quantity' => 10],
            ['quantity' => 2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
        ],
        12,
    ],
    'just-adjust' => [
        [
            ['quantity' => 2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
        ],
        2,
    ],
    'set-and-adjust-negative' => [
        [
            ['quantity' => 10],
            ['quantity' => -2, 'updateAction' => InventoryUpdateQuantityType::ADJUST],
        ],
        8,
    ],
]);
