<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

/**
 * Dispatched before a new line item is added to an order. Set `$isValid` to `false` to prevent it from being added.
 */
class LineItemAdding
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public LineItem $lineItem,
    ) {
    }
}
