<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Shipping\Contracts\ShippingMethodInterface;
use Illuminate\Support\Collection;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Shipping\Events\ShippingMethodsResolving} instead.
 */
class RegisterAvailableShippingMethodsEvent extends Event
{
    /**
     * @var Order The order the shipping method should be available for
     */
    public Order $order;

    /**
     * @var Collection<array-key, ShippingMethodInterface>|null The shipping methods available to the order.
     */
    private ?Collection $_shippingMethods = null;

    public function setShippingMethods(Collection|array $shippingMethods): void
    {
        $this->_shippingMethods = collect($shippingMethods);
    }

    /**
     * @return Collection<array-key, ShippingMethodInterface>
     */
    public function getShippingMethods(): Collection
    {
        return $this->_shippingMethods ??= collect();
    }
}
