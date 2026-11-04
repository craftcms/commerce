<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Commerce\Order\Elements\Order;

/**
 * Dispatched before an order is marked as complete.
 */
class OrderCompleting
{
    public function __construct(
        public Order $order,
    ) {
    }
}
