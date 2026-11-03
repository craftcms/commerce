<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Order\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Data\OrderNotice;
use CraftCms\Commerce\Order\Elements\Order;

/**
 * Dispatched before a new notice is saved with its order. Set `$isValid` to `false` to prevent it from being saved.
 */
class OrderNoticeSaving
{
    use ValidatableEvent;

    public function __construct(
        public Order $order,
        public OrderNotice $orderNotice,
    ) {
    }
}
