<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\elements\Order;

/**
 * Shipping method option model.
 *
 * @property float $price the price of the shipping method option for the order.
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class ShippingMethodOption extends ShippingMethod
{
    /**
     * @var Order
     */
    private $_order;

    /**
     * @var float Price of the shipping method option
     */
    public $price;

    /**
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */
    public function currencyAttributes(): array
    {
        $attributes = parent::currencyAttributes();

        $attributes[] = 'price';

        return $attributes;
    }

    /**
     * @return string
     */
    protected function getCurrency(): string
    {
        return $this->_order->currency ?? parent::getCurrency();
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        return $this->price;
    }

    /**
     * @param $order
     * @since 3.1.10
     */
    public function setOrder($order)
    {
        $this->_order = $order;
    }
}
