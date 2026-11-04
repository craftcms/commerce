<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Product\Variant\Events\VariantSnapshotFieldsResolving} instead.
 */
class CustomizeVariantSnapshotFieldsEvent extends Event
{
    /**
     * @var Variant The variant
     */
    public Variant $variant;

    /**
     * @var array|null The fields to be captured
     */
    public ?array $fields = null;
}
