<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Events;

use craft\commerce\elements\Order;
use Illuminate\Support\Collection;

class RegisterAvailableShippingMethodsEvent
{
    public Order $order;
    private ?Collection $_shippingMethods = null;

    public function setShippingMethods(Collection|array $shippingMethods): void
    {
        if (!$shippingMethods instanceof Collection) {
            $shippingMethods = collect($shippingMethods);
        }

        $this->_shippingMethods = $shippingMethods;
    }

    public function getShippingMethods(): Collection
    {
        if ($this->_shippingMethods === null) {
            $this->_shippingMethods = collect();
        }

        return $this->_shippingMethods;
    }
}
