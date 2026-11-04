<?php

declare(strict_types=1);

use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
use craft\commerce\events\ProductTypeEvent;
use craft\commerce\events\PurchasableAvailableEvent;
use craft\commerce\events\PurchasableOutOfStockPurchasesAllowedEvent;
use craft\commerce\events\PurchasableShippableEvent;
use craft\commerce\events\RegisterAvailableShippingMethodsEvent;
use craft\commerce\services\ProductTypes as LegacyProductTypes;
use craft\commerce\services\Purchasables as LegacyPurchasables;
use craft\commerce\services\ShippingMethods as LegacyShippingMethods;
use craft\events\ElementCriteriaEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Events\ProductParentSelectionCriteriaResolving;
use CraftCms\Commerce\Product\Events\ProductSnapshotFieldsResolving;
use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use CraftCms\Commerce\Product\ProductType\Events\ProductTypeSaved;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Events\VariantSnapshotDataResolving;
use CraftCms\Commerce\Purchasable\Events\PurchasableAvailabilityResolving;
use CraftCms\Commerce\Purchasable\Events\PurchasableOutOfStockPurchasesResolving;
use CraftCms\Commerce\Purchasable\Events\PurchasableShippabilityResolving;
use CraftCms\Commerce\Shipping\Data\ShippingMethod;
use CraftCms\Commerce\Shipping\Events\ShippingMethodsResolving;
use yii\base\Event as YiiEvent;

// These test the legacy craft\commerce event bridge, which needs the yii2 adapter.
pest()->group('yii2-adapter');

afterEach(function() {
    YiiEvent::offAll();
});

test('legacy listeners can change purchasable availability, shippability and out-of-stock purchases', function() {
    YiiEvent::on(LegacyPurchasables::class, LegacyPurchasables::EVENT_PURCHASABLE_AVAILABLE, fn(PurchasableAvailableEvent $event) => $event->isAvailable = false);
    YiiEvent::on(LegacyPurchasables::class, LegacyPurchasables::EVENT_PURCHASABLE_SHIPPABLE, fn(PurchasableShippableEvent $event) => $event->isShippable = false);
    YiiEvent::on(LegacyPurchasables::class, LegacyPurchasables::EVENT_PURCHASABLE_OUT_OF_STOCK_PURCHASES_ALLOWED, fn(PurchasableOutOfStockPurchasesAllowedEvent $event) => $event->outOfStockPurchasesAllowed = true);

    $variant = new Variant();
    event($available = new PurchasableAvailabilityResolving($variant, true));
    event($shippable = new PurchasableShippabilityResolving($variant, true));
    event($outOfStock = new PurchasableOutOfStockPurchasesResolving($variant));

    expect($available->isAvailable)->toBeFalse()
        ->and($shippable->isShippable)->toBeFalse()
        ->and($outOfStock->outOfStockPurchasesAllowed)->toBeTrue();
});

test('legacy listeners can change the shipping methods available to an order', function() {
    $order = new Order();
    $method = new ShippingMethod();
    $received = null;

    YiiEvent::on(LegacyShippingMethods::class, LegacyShippingMethods::EVENT_REGISTER_AVAILABLE_SHIPPING_METHODS, function(RegisterAvailableShippingMethodsEvent $event) use ($method, &$received) {
        $received = $event->order;
        $event->setShippingMethods([$method]);
    });

    event($event = new ShippingMethodsResolving($order, collect()));

    expect($received)->toBe($order)
        ->and($event->shippingMethods->all())->toBe([$method]);
});

test('legacy listeners receive product type saves', function() {
    $productType = new ProductType();
    $received = null;

    YiiEvent::on(LegacyProductTypes::class, LegacyProductTypes::EVENT_AFTER_SAVE_PRODUCTTYPE, function(ProductTypeEvent $event) use (&$received) {
        $received = [$event->productType, $event->isNew];
    });

    event(new ProductTypeSaved($productType, true));

    expect($received)->toBe([$productType, true]);
});

test('legacy listeners on the craft\commerce\elements\Variant alias can customize snapshots', function() {
    $variant = new Variant();
    $senders = [];

    YiiEvent::on('craft\commerce\elements\Variant', Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, function(CustomizeProductSnapshotFieldsEvent $event) use (&$senders) {
        $senders[] = $event->sender;
        $event->fields = ['productField'];
    });
    YiiEvent::on('craft\commerce\elements\Variant', Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, function(CustomizeVariantSnapshotDataEvent $event) use (&$senders) {
        $senders[] = $event->sender;
        $event->fieldData['redacted'] = true;
    });

    event($fields = new ProductSnapshotFieldsResolving($variant, null));
    event($data = new VariantSnapshotDataResolving($variant, ['sku' => 'ABC']));

    expect($fields->fields)->toBe(['productField'])
        ->and($data->fieldData)->toBe(['sku' => 'ABC', 'redacted' => true])
        ->and($senders)->toBe([$variant, $variant]);
});

test('legacy listeners on the craft\commerce\elements\Product alias can change parent selection criteria', function() {
    $product = new Product();

    YiiEvent::on('craft\commerce\elements\Product', Product::EVENT_DEFINE_PARENT_SELECTION_CRITERIA, function(ElementCriteriaEvent $event) {
        $event->criteria['level'] = 1;
    });

    event($event = new ProductParentSelectionCriteriaResolving($product, ['typeId' => 1]));

    expect($event->criteria)->toBe(['typeId' => 1, 'level' => 1]);
});
