<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Events;

use CraftCms\Commerce\Order\Elements\Order;
use Illuminate\Support\Collection;

class RegisterAvailableShippingMethodsEvent
{
    private ?Collection $_shippingMethods = null;

    public function __construct(
        public Order $order,
    ) {
    }

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
