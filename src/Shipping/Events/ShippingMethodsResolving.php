<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use Illuminate\Support\Collection;

/**
 * Dispatched when resolving the shipping methods that may be available to an order. Listeners may add, remove, or replace `$shippingMethods`.
 */
class ShippingMethodsResolving
{
    public function __construct(
        public Order $order,
        /** @var Collection<array-key, ShippingMethodInterface> */
        public Collection $shippingMethods,
    ) {
    }
}
