<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\ProductType\Data\ProductType;

class ProductTypeEvent
{
    public ?ProductType $productType = null;
    public bool $isNew = false;
}
