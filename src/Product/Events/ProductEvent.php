<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Product\Elements\Product;

class ProductEvent
{
    use ValidatableEvent;

    public function __construct(
        public Product $product,
        public bool $isNew,
    ) {
    }
}
