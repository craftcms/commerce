<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Product\Events;

use CraftCms\Commerce\Product\Elements\Product;

/**
 * Dispatched when defining the criteria for a product's parent selection field. Listeners may change `$criteria`.
 */
class ProductParentSelectionCriteriaResolving
{
    public function __construct(
        public Product $product,
        /** @var array<string, mixed> */
        public array $criteria,
    ) {
    }
}
