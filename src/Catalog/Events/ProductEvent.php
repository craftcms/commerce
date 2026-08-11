<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Product;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class ProductEvent
{
    use ValidatableEvent;

    public Product $product;
    public bool $isNew;
}
