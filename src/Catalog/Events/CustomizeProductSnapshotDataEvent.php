<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Catalog\Events;

use craft\commerce\elements\Product;

class CustomizeProductSnapshotDataEvent
{
    public Product $product;
    public array $fieldData;
}
