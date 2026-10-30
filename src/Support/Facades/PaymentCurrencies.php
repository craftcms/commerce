<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Support\Facades;

use Illuminate\Support\Facades\Facade;
use Override;

/**
 * @method static float getRateFor(\CraftCms\Commerce\Payment\Data\PaymentCurrency $currency, \CraftCms\Commerce\Payment\Data\Transaction|null $transaction = null)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentCurrency|null getPaymentCurrencyById(int $id, int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getAllPaymentCurrencies(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentCurrency|null getPaymentCurrencyByIso(string $iso, int|null $storeId = null)
 * @method static string getPrimaryPaymentCurrencyIso(int|null $storeId = null)
 * @method static \CraftCms\Commerce\Payment\Data\PaymentCurrency|null getPrimaryPaymentCurrency(int|null $storeId = null)
 * @method static \Illuminate\Support\Collection getNonPrimaryPaymentCurrencies(int|null $storeId = null)
 * @method static float convert(float $amount, string $iso)
 * @method static float convertCurrency(float $amount, string $fromCurrency, string $toCurrency, bool $round = false)
 * @method static bool savePaymentCurrency(\CraftCms\Commerce\Payment\Data\PaymentCurrency $model, bool $runValidation = true)
 * @method static bool deletePaymentCurrencyById(int $id)
 * @method static \Money\Money convertAmount(\Money\Money $amount, \Money\Currency|string $currency, int|null $storeId = null)
 *
 * @see \CraftCms\Commerce\Payment\PaymentCurrencies
 */
class PaymentCurrencies extends Facade
{
    #[Override]
    protected static function getFacadeAccessor(): string
    {
        return \CraftCms\Commerce\Payment\PaymentCurrencies::class;
    }
}
