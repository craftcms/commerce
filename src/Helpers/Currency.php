<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Helpers;

use CraftCms\Cms\Cp\FormFields;
use CraftCms\Cms\Support\Facades\I18N;
use CraftCms\Cms\Twig\Exceptions\TemplateLoaderException;
use CraftCms\Commerce\Order\Exceptions\CurrencyException;
use CraftCms\Commerce\Payment\Currencies;
use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Store\Stores;
use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Formatter\IntlMoneyFormatter;

class Currency
{
    public static function round(float $amount, PaymentCurrency|string|MoneyCurrency|null $currency = null): float
    {
        if (!$currency) {
            $currency = app(Stores::class)->getCurrentStore()->getCurrency();
        }

        if ($currency instanceof PaymentCurrency) {
            $currency = new MoneyCurrency($currency->getAlphabeticCode());
        }

        if (is_string($currency)) {
            $currency = new MoneyCurrency($currency);
        }

        $moneyFormatter = new DecimalMoneyFormatter(new ISOCurrencies());
        return (float)$moneyFormatter->format(app(Currencies::class)->getTeller($currency)->convertToMoney($amount));
    }

    /**
     * @throws CurrencyException
     * @throws \InvalidArgumentException
     */
    public static function defaultDecimals(): int
    {
        return app(PaymentCurrencies::class)->getPrimaryPaymentCurrency()->getSubUnit();
    }

    /**
     * @throws CurrencyException
     * @throws \InvalidArgumentException
     */
    public static function formatAsCurrency($amount, mixed $currency = null, bool $convert = false, bool $format = true, bool $stripZeros = false): string
    {
        if (!$convert && !$format) {
            return $amount;
        }

        $currencyIso = app(Stores::class)->getCurrentStore()->getCurrency();

        if (is_string($currency)) {
            $currencyIso = $currency;
        }

        if ($currency instanceof PaymentCurrency) {
            $currencyIso = $currency->iso;
        }

        if ($convert) {
            $currency = app(PaymentCurrencies::class)->getPaymentCurrencyByIso($currencyIso);
            if (!$currency) {
                throw new \BadMethodCallException('Trying to convert to a currency that is not configured');
            }
        }

        if ($convert && $currencyIso !== app(Stores::class)->getCurrentStore()->getCurrency()) {
            $amount = app(PaymentCurrencies::class)->convert((float)$amount, $currencyIso);
        }

        if ($format) {
            $numberFormatter = new \NumberFormatter((string)I18N::getFormattingLocale(), \NumberFormatter::CURRENCY);

            if ($stripZeros && (int)$amount == $amount) {
                $numberFormatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, 0);
                $numberFormatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, 0);
            }

            $moneyFormatter = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies());
            $money = app(Currencies::class)->getTeller($currencyIso)->convertToMoney($amount);

            return $moneyFormatter->format($money);
        }

        return (string)$amount;
    }

    /**
     * @throws \InvalidArgumentException
     * @throws TemplateLoaderException
     */
    public static function moneyInputHtml(mixed $value, array $config = []): string
    {
        $config += [
            'showCurrency' => true,
            'size' => 6,
            'decimals' => 2,
            'value' => $value,
        ];

        if (isset($config['currency'])) {
            $config['decimals'] = app(Currencies::class)->getSubunitFor($config['currency']);
        }

        return FormFields::moneyInputHtml($config);
    }
}
