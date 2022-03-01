<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\models;

use craft\commerce\behaviors\CurrencyAttributeBehavior;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

/**
 * Shipping method option model.
 *
 * @property float $price the price of the shipping method option for the order.
 * @property Order $order
 * @property string $currency
 * @property-read string $priceAsCurrency
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 3.1
 */
class ShippingMethodOption extends ShippingMethod
{
    /**
     * @var Order
     */
    private Order $_order;

    /**
     * @var float Price of the shipping method option
     */
    public float $price;

    /**
     * @var boolean
     */
    public bool $matchesOrder;

    /**
     * @throws InvalidConfigException
     */
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();

        $behaviors['currencyAttributes'] = [
            'class' => CurrencyAttributeBehavior::class,
            'defaultCurrency' => Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso(),
            'currencyAttributes' => $this->currencyAttributes(),
        ];

        return $behaviors;
    }

    /**
     * The attributes on the order that should be made available as formatted currency.
     */
    public function currencyAttributes(): array
    {
        $attributes = [];
        $attributes[] = 'price';
        return $attributes;
    }

    protected function getCurrency(): string
    {
        return $this->_order->currency;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @since 3.1.10
     */
    public function setOrder(Order $order): void
    {
        $this->_order = $order;
    }
}
