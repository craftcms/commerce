<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\elements\traits;

use Craft;
use craft\commerce\elements\Order;
use yii\base\InvalidConfigException;

trait OrderDeprecatedTrait
{
    /**
     * @param bool $value
     * @throws \craft\errors\DeprecationException
     * @deprecated as of 3.0
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
     * @deprecated as of 3.0
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
