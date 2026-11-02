<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Commerce\Product\Elements\Product;
use CraftCms\Commerce\Product\Variant\Elements\Variant;

/**
 * Dispatched before a variant captures its product's snapshot. Listeners may add custom field handles to `$fields`; custom fields aren't captured by default.
 */
class ProductSnapshotFieldsResolving
{
    public function __construct(
        public Variant $variant,
        public ?Product $product,
        /** @var string[]|null */
        public ?array $fields = null,
    ) {
    }
}
