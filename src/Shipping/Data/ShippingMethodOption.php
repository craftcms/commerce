<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Shipping\Data;

use CraftCms\Cms\Support\Arr;
use CraftCms\Commerce\Helpers\Currency;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use Override;

class ShippingMethodOption extends ShippingMethod
{
    /** @phpstan-ignore-next-line */
    private Order $_order;

    public float $price;

    public bool $matchesOrder;

    public ?ShippingMethodInterface $shippingMethod = null;

    /**
     * An option is a per-order, computed view of a shipping method rather than a persisted record
     * of its own, so its inherited dateCreated/dateUpdated are never meaningful.
     */
    #[Override]
    public function fields(): array
    {
        return Arr::except(parent::fields(), ['dateCreated', 'dateUpdated']);
    }

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
