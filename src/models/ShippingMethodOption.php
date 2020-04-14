<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use Craft;
use craft\commerce\elements\Order;
use yii\base\InvalidCallException;

/**
 * Shipping method option model.
 *
 * @property Order $order the order the shipping method options was create for.
 * @property-read float $price the price of the shipping method option for the order.
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class ShippingMethodOption extends ShippingMethod
{
    private $_price;

    private $_order;

    /**
     * @inheritDoc
     */
    public function attributes()
    {
        $attributes = parent::attributes();
        $attributes[] = 'price';

        return $attributes;
    }

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
     * @param Order $order
     */
    public function setOrder(Order $order)
    {
        $this->_order = $order;
    }

    /**
     * @return float
     */
    public function getPrice()
    {
        if (!$this->_order) {
            throw new InvalidCallException('Can not call getPrice() before setting the order.');
        }

        $this->_price = $this->getPriceForOrder($this->_order);

        return $this->_price;
    }
}
