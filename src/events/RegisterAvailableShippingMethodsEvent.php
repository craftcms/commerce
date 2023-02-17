<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use Illuminate\Support\Collection;
use yii\base\Event;

/**
 * RegisterAvailableShippingMethodsEvent class.
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.0
 */
class RegisterAvailableShippingMethodsEvent extends Event
{
    /**
     * @var Order The order the shipping method should be available for
     */
    public Order $order;

    /**
     * @var Collection<ShippingMethodInterface>|null The shipping methods available to the order.
     * @see getShippingMethods()
     * @see setShippingMethods()
     */
    private ?Collection $_shippingMethods = null;

    /**
     * @param Collection|array $shippingMethods
     * @return void
     * @since 5.0.0
     */
    public function setShippingMethods(Collection|array $shippingMethods): void
    {
        if (!$shippingMethods instanceof Collection) {
            $shippingMethods = collect($shippingMethods);
        }

        $this->_shippingMethods = $shippingMethods;
    }

    /**
     * @return Collection<ShippingMethodInterface>
     * @since 5.0.0
     */
    public function getShippingMethods(): Collection
    {
        if ($this->_shippingMethods === null) {
            $this->_shippingMethods = collect();
        }

        return $this->_shippingMethods;
    }
}
