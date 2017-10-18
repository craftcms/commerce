<?php

namespace craft\commerce\helpers;

use craft\commerce\Plugin;

/**
 * Class Currency
 *
 * @author    Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @copyright Copyright (c) 2015, Pixel & Tonic, Inc.
 * @license   https://craftcommerce.com/license Craft Commerce License Agreement
 * @see       https://craftcommerce.com
 * @package   Commerce\Helpers
 * @since     1.0
 */
class Currency
{
    /**
     * Rounds the amount as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float                $amount
     * @param \craft\commerce\models\Currency|null $currency
     *
     * @return float
     */
    public static function round($amount, $currency = null): float
    {
        if (!$currency) {
            $defaultPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
            $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
        }

        $decimals = $currency->minorUnit;

        return round($amount, $decimals);
    }

    public static function defaultDecimals()
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        $decimals = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($currency)->minorUnit;

        return $decimals;
    }
}
