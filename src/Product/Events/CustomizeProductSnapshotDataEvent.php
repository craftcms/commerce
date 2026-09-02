<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Commerce\Product\Elements\Product;

class CustomizeProductSnapshotDataEvent
{
    public function __construct(
        public Product $product,
        public array $fieldData,
    ) {
    }
}
