<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Data;

use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;

class ShippingMethodOption extends ShippingMethod
{
    /** @phpstan-ignore-next-line */
    private Order $_order;

    public float $price;

    public bool $matchesOrder;

    public ?ShippingMethodInterface $shippingMethod = null;

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getPriceAsCurrency(): string
    {
        return Currency::formatAsCurrency($this->price, $this->getStore()->getCurrency());
    }

    public function setOrder(Order $order): void
    {
        $this->_order = $order;
    }
}
