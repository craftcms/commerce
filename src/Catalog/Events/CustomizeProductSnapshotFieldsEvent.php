<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use CraftCms\Commerce\Catalog\Elements\Product;

class CustomizeProductSnapshotFieldsEvent
{
    public function __construct(
        public Product $product,
        public ?array $fields = null,
    ) {}
}
