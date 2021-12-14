<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use craft\commerce\models\Currency as CurrencyModel;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use yii\base\InvalidCallException;

/**
 * Class Currency
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since 2.0
 */
class Currency
{
    /**
     * Rounds the amount as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amount
     * @param PaymentCurrency|null $currency
     * @return float
     */
    public static function round($amount, $currency = null): float
    {
        if (!$currency) {
            $defaultPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
            $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
        }

        $decimals = $currency->minorUnit;

        // If $amount is string it throws round error on PHP 8
        // @todo to be removed on Commerce 4 - ensure $amount is of float type
        $amount = (float)$amount;

        return round($amount, $decimals);
    }

    /**
     * @return int
     */
    public static function defaultDecimals(): int
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        $decimals = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($currency)->minorUnit;

        return $decimals;
    }

    /**
     * Formats and optionally converts a currency amount into the supplied valid payment currency as per the rate setup in payment currencies.
     *
     * @param      $amount
     * @param      $currency
     * @param bool $convert
     * @param bool $format
     * @param bool $stripZeros
     * @return string
     */
    public static function formatAsCurrency($amount, $currency = null, $convert = false, $format = true, $stripZeros = false): string
    {
        // return input if no currency passed, and both convert and format are false.
        if (!$convert && !$format) {
            return $amount;
        }

        $currencyIso = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();

        if (is_string($currency)) {
            $currencyIso = $currency;
        }

        if ($currency instanceof PaymentCurrency) {
            $currencyIso = $currency->iso;
        }

        if ($currency instanceof CurrencyModel) {
            $currencyIso = $currency->alphabeticCode;
        }

        if ($convert) {
            $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currencyIso);
            if (!$currency) {
                throw new InvalidCallException('Trying to convert to a currency that is not configured');
            }
        }

        if ($convert) {
            $amount = Plugin::getInstance()->getPaymentCurrencies()->convert((float)$amount, $currencyIso);
        }

        if ($format) {
            // Round it before formatting
            if ($currencyData = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($currencyIso)) {
                $amount = self::round($amount, $currencyData); // Will round to the right minorUnits
            }

            $amount = \Craft::$app->getFormatter()->asCurrency($amount, $currencyIso, [], [], $stripZeros);
        }

        return (string)$amount;
    }
}
