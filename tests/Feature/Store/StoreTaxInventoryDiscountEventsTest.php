<?php

declare(strict_types=1);

use craft\commerce\events\DeleteStoreEvent;
use craft\commerce\events\DiscountAdjustmentsEvent;
use craft\commerce\events\InventoryMovementEvent;
use craft\commerce\events\StoreEvent;
use craft\commerce\events\TaxEngineEvent;
use craft\commerce\events\TaxIdValidatorsEvent;
use craft\commerce\events\UpdateInventoryLevelEvent;
use craft\commerce\services\Inventory as LegacyInventory;
use craft\commerce\services\Stores as LegacyStores;
use craft\commerce\services\Taxes as LegacyTaxes;
use CraftCms\Commerce\Inventory\Data\InventoryManualMovement;
use CraftCms\Commerce\Inventory\Data\UpdateInventoryLevel;
use CraftCms\Commerce\Inventory\Events\InventoryLevelUpdated;
use CraftCms\Commerce\Inventory\Events\InventoryMoved;
use CraftCms\Commerce\Order\Adjuster\Discount as DiscountAdjuster;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Promotion\Data\Discount;
use CraftCms\Commerce\Promotion\Events\DiscountAdjustmentsCreated;
use CraftCms\Commerce\Store\Data\Store;
use CraftCms\Commerce\Store\Events\StoreDeleting;
use CraftCms\Commerce\Store\Events\StoreSaving;
use CraftCms\Commerce\Tax\Engines\Tax;
use CraftCms\Commerce\Tax\Events\TaxEngineResolving;
use CraftCms\Commerce\Tax\Events\TaxIdValidatorsResolving;
use yii\base\Event as YiiEvent;

// These test the legacy craft\commerce event bridge, which needs the yii2 adapter.
pest()->group('yii2-adapter');

afterEach(function() {
    YiiEvent::offAll();
});

test('legacy listeners can cancel saving and deleting a store', function() {
    YiiEvent::on(LegacyStores::class, LegacyStores::EVENT_BEFORE_SAVE_STORE, fn(StoreEvent $event) => $event->isValid = false);
    YiiEvent::on(LegacyStores::class, LegacyStores::EVENT_BEFORE_DELETE_STORE, fn(DeleteStoreEvent $event) => $event->isValid = false);

    event($saving = new StoreSaving(new Store(), true));
    event($deleting = new StoreDeleting(new Store()));

    expect($saving->isValid)->toBeFalse()
        ->and($deleting->isValid)->toBeFalse();
});

test('legacy listeners can change the tax engine and tax ID validators', function() {
    $engine = new Tax();

    YiiEvent::on(LegacyTaxes::class, LegacyTaxes::EVENT_REGISTER_TAX_ENGINE, fn(TaxEngineEvent $event) => $event->engine = $engine);
    YiiEvent::on(LegacyTaxes::class, LegacyTaxes::EVENT_REGISTER_TAX_ID_VALIDATORS, fn(TaxIdValidatorsEvent $event) => $event->validators = ['custom']);

    event($engineEvent = new TaxEngineResolving(new Tax()));
    event($validatorsEvent = new TaxIdValidatorsResolving(['vat']));

    expect($engineEvent->engine)->toBe($engine)
        ->and($validatorsEvent->validators)->toBe(['custom']);
});

test('legacy listeners receive inventory level updates and movements', function() {
    $update = new UpdateInventoryLevel();
    $movement = new InventoryManualMovement();
    $received = [];

    YiiEvent::on(LegacyInventory::class, LegacyInventory::EVENT_AFTER_EXECUTE_UPDATE_INVENTORY_LEVEL, function(UpdateInventoryLevelEvent $event) use (&$received) {
        $received[] = $event->updateInventoryLevel;
    });
    YiiEvent::on(LegacyInventory::class, LegacyInventory::EVENT_AFTER_EXECUTE_INVENTORY_MOVEMENT, function(InventoryMovementEvent $event) use (&$received) {
        $received[] = $event->inventoryMovement;
    });

    event(new InventoryLevelUpdated($update));
    event(new InventoryMoved($movement));

    expect($received)->toBe([$update, $movement]);
});

test('legacy discount adjustment listeners on the craft\commerce\adjusters\Discount alias can change or discard adjustments', function(bool $discard) {
    YiiEvent::on('craft\commerce\adjusters\Discount', DiscountAdjuster::EVENT_AFTER_DISCOUNT_ADJUSTMENTS_CREATED, function(DiscountAdjustmentsEvent $event) use ($discard) {
        $event->adjustments = [];
        $event->isValid = !$discard;
    });

    event($event = new DiscountAdjustmentsCreated(new Order(), new Discount(), ['adjustment']));

    expect($event->adjustments)->toBe([])
        ->and($event->isValid)->toBe(!$discard);
})->with([
    'change' => [false],
    'discard' => [true],
]);
