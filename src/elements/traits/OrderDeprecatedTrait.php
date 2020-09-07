<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\base\ShippingMethodInterface;
use craft\commerce\elements\Order;
use craft\commerce\Plugin;
use yii\base\InvalidConfigException;

trait OrderDeprecatedTrait
{

    /**
     * @return ShippingMethodInterface[]
     * @throws \craft\errors\DeprecationException
     * @deprecated as of 3.1.8
     *
     */
    public function getAvailableShippingMethods(): array
    {
        Craft::$app->getDeprecator()->log('Order::getAvailableShippingMethods()', '`Order::getAvailableShippingMethods()` has been deprecated. Use `Order::getAvailableShippingMethodOptions().`');

        /** @var Order $this */
        return Plugin::getInstance()->getShippingMethods()->getAvailableShippingMethods($this);
    }

    /**
     * @param bool $value
     * @throws \craft\errors\DeprecationException
     * @deprecated as of 3.0
     */
    public function setShouldRecalculateAdjustments(bool $value)
    {
        Craft::$app->getDeprecator()->log('Order::setShouldRecalculateAdjustments()', '`Order::setShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.');

        if ($value) {
            /** @var Order $this */
            $this->setRecalculationMode(Order::RECALCULATION_MODE_ALL);
        } else {
            /** @var Order $this */
            $this->setRecalculationMode(Order::RECALCULATION_MODE_NONE);
        }
    }

    /**
     * @return bool
     * @throws InvalidConfigException|\craft\errors\DeprecationException
     * @deprecated as of 3.0
     */
    public function getShouldRecalculateAdjustments(): bool
    {
        Craft::$app->getDeprecator()->log('Order::getShouldRecalculateAdjustments()', '`Order::getShouldRecalculateAdjustments()` has been deprecated. Use `Order::recalculationMode` instead.');

        /** @var Order $this */
        if ($this->getRecalculationMode() == Order::RECALCULATION_MODE_ALL) {
            return true;
        }

        /** @var Order $this */
        if ($this->getRecalculationMode() == Order::RECALCULATION_MODE_ADJUSTMENTS_ONLY) {
            throw new InvalidConfigException('Order::getShouldRecalculateAdjustments() has been deprecated.');
        }

        return false;
    }
}
