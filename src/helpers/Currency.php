<?php

/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\helpers;

use Craft;
use craft\commerce\errors\CurrencyException;
use craft\commerce\models\Currency as CurrencyModel;
use craft\commerce\models\PaymentCurrency;
use craft\commerce\Plugin;
use yii\base\InvalidCallException;
use yii\base\InvalidConfigException;

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
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return float
     */
    public static function round(float $amount, PaymentCurrency|CurrencyModel|null $currency = null): float
    {
        if (!$currency) {
            $defaultPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
            $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
        }

        $decimals = $currency->minorUnit;
        return round($amount, $decimals);
    }

    /**
     * Subtracts amountB from amountA in a reliable way as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amountA
     * @param float $amountB
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return float
     */
    public static function subtract(float $amountA, float $amountB, PaymentCurrency|CurrencyModel|null $currency = null): float
    {
        $adjustedA = Currency::baseAmount($amountA, $currency);
        $adjustedB = Currency::baseAmount($amountB, $currency);

        return Currency::floatAmount($adjustedA - $adjustedB);
    }

    /**
     * Adds the two amounts in a reliable way as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amountA
     * @param float $amountB
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return float
     */
    public static function add(float $amountA, float $amountB, PaymentCurrency|CurrencyModel|null $currency = null): float
    {
        $adjustedA = Currency::baseAmount($amountA, $currency);
        $adjustedB = Currency::baseAmount($amountB, $currency);

        return Currency::floatAmount($adjustedA + $adjustedB);
    }

    /**
     * Compares the equality of two amounts in a reliable way as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amountA
     * @param float $amountB
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return bool
     */
    public static function equals(float $amountA, float $amountB, PaymentCurrency|CurrencyModel|null $currency = null): bool
    {
        $adjustedA = Currency::baseAmount($amountA, $currency);
        $adjustedB = Currency::baseAmount($amountB, $currency);

        return $adjustedA == $adjustedB;
    }

    /**
     * Compares if amountA is greater than amountB in a reliable way as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amountA
     * @param float $amountB
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return bool
     */
    public static function greaterThan(float $amountA, float $amountB, PaymentCurrency|CurrencyModel|null $currency = null): bool
    {
        $adjustedA = Currency::baseAmount($amountA, $currency);
        $adjustedB = Currency::baseAmount($amountB, $currency);

        return $adjustedA > $adjustedB;
    }

    /**
     * Compares if amountA is less than amountB in a reliable way as per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amountA
     * @param float $amountB
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return bool
     */
    public static function lessThan(float $amountA, float $amountB, PaymentCurrency|CurrencyModel|null $currency = null): bool
    {
        $adjustedA = Currency::baseAmount($amountA, $currency);
        $adjustedB = Currency::baseAmount($amountB, $currency);

        return $adjustedA < $adjustedB;
    }

    /**
     * Converts the amount to an integer while maintaining the required amount of decimal places per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param float $amount
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return int
     */
    private static function baseAmount(float $amount, PaymentCurrency|CurrencyModel|null $currency = null): int
    {
        if (!$currency) {
            $defaultPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
            $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
        }


        $decimals = $currency->minorUnit;
        return intval(floatval(Currency::round($amount, $currency) . '') * pow(10, $decimals));
    }

    /**
     * Converts the amount from an integer while maintaining the required amount of decimal places per the currency minor unit information. Not passing
     * a currency model results in rounding in default currency.
     *
     * @param int $amount
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return float
     */
    private static function floatAmount(int $amount, PaymentCurrency|CurrencyModel|null $currency = null): float
    {
        if (!$currency) {
            $defaultPaymentCurrency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrency();
            $currency = Plugin::getInstance()->getCurrencies()->getCurrencyByIso($defaultPaymentCurrency->iso);
        }


        $decimals = $currency->minorUnit;
        return floatval(Currency::round($amount / pow(10, $decimals), $currency) . '');
    }

    public static function defaultDecimals(): int
    {
        $currency = Plugin::getInstance()->getPaymentCurrencies()->getPrimaryPaymentCurrencyIso();
        return Plugin::getInstance()->getCurrencies()->getCurrencyByIso($currency)->minorUnit;
    }

    /**
     * Formats and optionally converts a currency amount into the supplied valid payment currency as per the rate setup in payment currencies.
     *
     * @param      $amount
     * @param mixed $currency
     * @param bool $convert
     * @param bool $format
     * @param bool $stripZeros
     * @return string
     * @throws CurrencyException
     * @throws InvalidConfigException
     */
    public static function formatAsCurrency($amount, mixed $currency = null, bool $convert = false, bool $format = true, bool $stripZeros = false): string
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

            $amount = Craft::$app->getFormatter()->asCurrency($amount, $currencyIso, [], [], $stripZeros);
        }

        return (string)$amount;
    }
}
