<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\LineItem\Data\LineItem;

/**
 * Dispatched before an order's line items are refreshed. Listeners may change `$lineItems`, or set `$recalculate` to recalculate the order afterward.
 */
class LineItemsRefreshing
{
    public function __construct(
        public Order $order,
        /** @var LineItem[] */
        public array $lineItems,
        public bool $recalculate = false,
    ) {
    }
}
