<?php

namespace craft\commerce\base;

use Craft;

trait OrderDeprecatedTrait
{
    /**
     * @return string
     * @deprecated
     */
    public function getOrderLocale(): string
    {
        Craft::$app->getDeprecator()->log('Order::getOrderLocale()', 'Order::getOrderLocale() has been deprecated. Use Order::orderLanguage instead.');

        return $this->orderLanguage;
    }

    /**
     * @return float
     * @deprecated
     */
    public function getTotalTax(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalTax()', 'Order::getTotalTax() has been deprecated. Use Order::getAdjustmentsTotalByType("tax") instead.');

        return $this->getAdjustmentsTotalByType('tax');
    }

    /**
     * @return float
     * @deprecated
     */
    public function getTotalTaxIncluded(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalTaxIncluded()', 'Order::getTax() has been deprecated. Use Order::getAdjustmentsTotalByType("taxIncluded") instead.');

        return $this->getAdjustmentsTotalByType('tax', true);
    }

    /**
     * @return float
     * @deprecated
     */
    public function getTotalDiscount(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalDiscount()', 'Order::getTotalDiscount() has been deprecated. Use Order::getAdjustmentsTotalByType("discount") instead.');

        return $this->getAdjustmentsTotalByType('discount');
    }

    /**
     * @return float
     * @deprecated
     */
    public function getTotalShippingCost(): float
    {
        Craft::$app->getDeprecator()->log('Order::getTotalShippingCost()', 'Order::getTotalShippingCost() has been deprecated. Use Order::getAdjustmentsTotalByType("shipping") instead.');

        return $this->getAdjustmentsTotalByType('shipping');
    }
}
