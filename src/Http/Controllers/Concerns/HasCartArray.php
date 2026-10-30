<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Http\Controllers\Concerns;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Order\Events\ModifyCartInfoEvent;

trait HasCartArray
{
    protected function cartArray(Order $cart): array
    {
        $extraFields = [
            'availableShippingMethodOptions',
            'billingAddress',
            'lineItems.snapshot',
            'notices',
            'shippingAddress',
        ];

        $cartInfo = $cart->toArray([], $extraFields);

        $event = new ModifyCartInfoEvent(
            cartInfo: $cartInfo,
            cart: $cart,
        );

        event($event);

        return $event->cartInfo;
    }
}
