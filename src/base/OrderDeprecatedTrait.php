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
}
