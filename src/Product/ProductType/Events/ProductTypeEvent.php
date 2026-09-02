<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\ProductType\Events;

use CraftCms\Commerce\Product\ProductType\Data\ProductType;

class ProductTypeEvent
{
    public function __construct(
        public ?ProductType $productType = null,
        public bool $isNew = false,
    ) {
    }
}
