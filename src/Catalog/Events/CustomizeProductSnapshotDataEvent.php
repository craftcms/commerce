<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Product;

class CustomizeProductSnapshotDataEvent
{
    public function __construct(
        public Product $product,
        public array $fieldData,
    ) {}
}
