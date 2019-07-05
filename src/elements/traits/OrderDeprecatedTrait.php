<?php

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\elements\Order;
use yii\base\InvalidConfigException;

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
        Craft::$app->getDeprecator()->log('Order::getTotalTaxIncluded()', 'Order::getTotalTaxIncluded() has been deprecated. Use Order::getAdjustmentsTotalByType("tax", true) instead.');

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

    /**
     * @param bool $value
     * @throws \craft\errors\DeprecationException
     * @deprecated as of 2.2
     */
    public function setShouldRecalculateAdjustments(bool $value)
    {
        Craft::$app->getDeprecator()->log('Order::setShouldRecalculateAdjustments()', 'Order::setShouldRecalculateAdjustments() has been deprecated. Use Order::recalculationMode instead.');

        if ($value) {
            $this->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        } else {
            $this->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        }
    }

    /**
     * @return bool
     * @throws InvalidConfigException
     * @deprecated as of 2.2
     */
    public function getShouldRecalculateAdjustments(): bool
    {
        Craft::$app->getDeprecator()->log('Order::getShouldRecalculateAdjustments()', 'Order::getShouldRecalculateAdjustments() has been deprecated. Use Order::recalculationMode instead.');

        if ($this->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            return true;
        }

        if ($this->getRecalculationMode() == Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY) {
            throw new InvalidConfigException('Order::getShouldRecalculateAdjustments() has been deprecated.');
        }

        return false;
    }
}
