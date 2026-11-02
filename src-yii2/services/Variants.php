<?php

namespace craft\commerce\services;

use craft\commerce\base\LegacyElementEvents;
use craft\commerce\events\CustomizeProductSnapshotDataEvent;
use craft\commerce\events\CustomizeProductSnapshotFieldsEvent;
use craft\commerce\events\CustomizeVariantSnapshotDataEvent;
use craft\commerce\events\CustomizeVariantSnapshotFieldsEvent;
use CraftCms\Commerce\Product\Events\ProductSnapshotDataResolving;
use CraftCms\Commerce\Product\Events\ProductSnapshotFieldsResolving;
use CraftCms\Commerce\Product\Variant\Elements\Variant;
use CraftCms\Commerce\Product\Variant\Events\VariantSnapshotDataResolving;
use CraftCms\Commerce\Product\Variant\Events\VariantSnapshotFieldsResolving;
use Illuminate\Support\Facades\Event;
use yii\base\Component;
use yii\base\InvalidConfigException;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Product\Variant\Variants::class)` instead.
 */
class Variants extends Component
{
    /**
     * Bridges the variant's Laravel events to the legacy `Variant::EVENT_*` Yii events.
     */
    public static function registerEvents(): void
    {
        $legacyVariant = \craft\commerce\elements\Variant::class;

        Event::listen(ProductSnapshotFieldsResolving::class, static function(ProductSnapshotFieldsResolving $event) use ($legacyVariant) {
            $legacyEvent = LegacyElementEvents::trigger($event->variant, $legacyVariant, Variant::EVENT_BEFORE_CAPTURE_PRODUCT_SNAPSHOT, new CustomizeProductSnapshotFieldsEvent([
                'product' => $event->product,
                'fields' => $event->fields,
            ]));
            $event->fields = $legacyEvent->fields;
        });

        Event::listen(ProductSnapshotDataResolving::class, static function(ProductSnapshotDataResolving $event) use ($legacyVariant) {
            $legacyEvent = LegacyElementEvents::trigger($event->variant, $legacyVariant, Variant::EVENT_AFTER_CAPTURE_PRODUCT_SNAPSHOT, new CustomizeProductSnapshotDataEvent([
                'product' => $event->product,
                'fieldData' => $event->fieldData,
            ]));
            $event->fieldData = $legacyEvent->fieldData;
        });

        Event::listen(VariantSnapshotFieldsResolving::class, static function(VariantSnapshotFieldsResolving $event) use ($legacyVariant) {
            $legacyEvent = LegacyElementEvents::trigger($event->variant, $legacyVariant, Variant::EVENT_BEFORE_CAPTURE_VARIANT_SNAPSHOT, new CustomizeVariantSnapshotFieldsEvent([
                'variant' => $event->variant,
                'fields' => $event->fields,
            ]));
            $event->fields = $legacyEvent->fields;
        });

        Event::listen(VariantSnapshotDataResolving::class, static function(VariantSnapshotDataResolving $event) use ($legacyVariant) {
            $legacyEvent = LegacyElementEvents::trigger($event->variant, $legacyVariant, Variant::EVENT_AFTER_CAPTURE_VARIANT_SNAPSHOT, new CustomizeVariantSnapshotDataEvent([
                'variant' => $event->variant,
                'fieldData' => $event->fieldData,
            ]));
            $event->fieldData = $legacyEvent->fieldData;
        });
    }

    /**
     * @return Variant[]
     */
    public function getAllVariantsByProductId(int $productId, ?int $siteId = null, bool $includeDisabled = true): array
    {
        return app(\CraftCms\Commerce\Product\Variant\Variants::class)->getAllVariantsByProductId($productId, $siteId, $includeDisabled);
    }

    public function getVariantById(int $variantId, ?int $siteId = null): ?Variant
    {
        return app(\CraftCms\Commerce\Product\Variant\Variants::class)->getVariantById($variantId, $siteId);
    }

    /**
     * @throws InvalidConfigException
     */
    public function getVariantGqlContentArguments(): array
    {
        return app(\CraftCms\Commerce\Product\Variant\Variants::class)->getVariantGqlContentArguments();
    }
}
