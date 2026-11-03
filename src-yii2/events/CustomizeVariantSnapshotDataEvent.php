<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Product\Variant\Elements\Variant;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Product\Variant\Events\VariantSnapshotDataResolving} instead.
 */
class CustomizeVariantSnapshotDataEvent extends Event
{
    /**
     * @var Variant The variant
     */
    public Variant $variant;

    /**
     * @var array The captured data
     */
    public array $fieldData;
}
