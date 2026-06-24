<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\commerce\elements\Product;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class ProductEvent
{
    use ValidatableEvent;

    public Product $product;
    public bool $isNew;
}
