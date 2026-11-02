<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

/**
 * Dispatched after a variant captures its product's snapshot. Listeners may customize, extend, or redact `$fieldData`.
 */
class ProductSnapshotDataResolving
{
    public function __construct(
        public Variant $variant,
        public ?Product $product,
        public array $fieldData,
    ) {
    }
}
