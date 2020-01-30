<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

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
}
