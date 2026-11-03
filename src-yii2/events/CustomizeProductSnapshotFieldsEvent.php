<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Product\Elements\Product;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Product\Events\ProductSnapshotFieldsResolving} instead.
 */
class CustomizeProductSnapshotFieldsEvent extends Event
{
    /**
     * @var Product|null The product
     */
    public ?Product $product = null;

    /**
     * @var array|null The fields to be captured
     */
    public ?array $fields = null;
}
