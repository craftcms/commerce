<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class ProductTypeEvent extends Event
{
    /**
     * @var ProductType|null The product type model associated with the event.
     */
    public ?ProductType $productType = null;

    /**
     * @var bool Whether the product type is brand new
     */
    public bool $isNew = false;
}
