<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
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
     * @var mixed
     */
    private $_order;

    /**
     * @var float Price of the shipping method option
     */
    public $price;

    /**
     * @return array
     */
    public function fields(): array
    {
        $fields = parent::fields();

        foreach ($this->currencyAttributes() as $attribute) {
            $fields[$attribute . 'AsCurrency'] = function($model, $attribute) {
                // Substr because attribute is returned with 'AsCurrency' appended
                $attribute = substr($attribute, 0, -10);
                $amount = $model->$attribute ?? 0;
                return Craft::$app->getFormatter()->asCurrency($amount, $this->_order->currency, [], [], true);
            };
        }

        return $fields;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     *
     * @return array
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'price';

        return $attributes;
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
