<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Events;

use CraftCms\Commerce\Product\ProductType\Data\ProductType;
use yii\base\Event;

class ProductTypeEvent extends Event
{
    public function __construct(
        public ?ProductType $productType = null,
        public bool $isNew = false,
    ) {
    }
}
