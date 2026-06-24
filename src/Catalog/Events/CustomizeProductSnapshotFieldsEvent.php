<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\commerce\elements\Product;

class CustomizeProductSnapshotFieldsEvent
{
    public Product $product;
    public ?array $fields = null;
}
