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
use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;
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
     * @param float $amount The amount as a decimal/float
     * @param PaymentCurrency|CurrencyModel|null $currency
     * @return float
     */
    public static function round(float $amount, PaymentCurrency|string|MoneyCurrency|null $currency = null): float
    {
        if (!$currency) {
            $currency = Plugin::getInstance()->getStores()->getCurrentStore()->getCurrency();
        }

        if ($currency instanceof PaymentCurrency) {
            $currency = new MoneyCurrency($currency->getAlphabeticCode());
        }

        if (is_string($currency)) {
            $currency = new MoneyCurrency($currency);
        }

        $moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
        return (float)$moneyFormatter->format(Plugin::getInstance()->getCurrencies()->getTeller($currency)->convertToMoney($amount));
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

        $currencyIso = Plugin::getInstance()->getStores()->getCurrentStore()->getCurrency();

        if (is_string($currency)) {
            $currencyIso = $currency;
        }

        if ($currency instanceof PaymentCurrency) {
            $currencyIso = $currency->iso;
        }

        if ($convert) {
            $currency = Plugin::getInstance()->getPaymentCurrencies()->getPaymentCurrencyByIso($currencyIso);
            if (!$currency) {
                throw new InvalidCallException('Trying to convert to a currency that is not configured');
            }
        }

        if ($convert && $currencyIso !== Plugin::getInstance()->getStores()->getCurrentStore()->getCurrency()) {
            $amount = Plugin::getInstance()->getPaymentCurrencies()->convert((float)$amount, $currencyIso);
        }

        if ($format) {
            $numberFormatter = new \NumberFormatter(Craft::$app->language, \NumberFormatter::CURRENCY);
            $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());
            $money = Plugin::getInstance()->getCurrencies()->getTeller($currencyIso)->convertToMoney($amount);

            return $moneyFormatter->format($money);
        }

        return (string)$amount;
    }
}
