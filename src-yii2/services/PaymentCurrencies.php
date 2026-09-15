<?php

namespace craft\commerce\services;

use craft\commerce\Plugin;
use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\Data\Transaction;

use CraftCms\Commerce\Payment\Events\PaymentCurrencyRateEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Money\Currency;
use Money\Money;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)` instead.
 */
class PaymentCurrencies extends Component
{
    /**
     * @since 5.7.0
     */
    public const EVENT_DEFINE_PAYMENT_CURRENCY_RATE = 'definePaymentCurrencyRate';

    public function getRateFor(PaymentCurrency $currency, ?Transaction $transaction = null): float
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getRateFor($currency, $transaction);
    }

    public function getPaymentCurrencyById(int $id, ?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getPaymentCurrencyById($id, $storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getAllPaymentCurrencies(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getAllPaymentCurrencies($storeId);
    }

    public function getPaymentCurrencyByIso(string $iso, ?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getPaymentCurrencyByIso($iso, $storeId);
    }

    public function getPrimaryPaymentCurrencyIso(?int $storeId = null): string
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso($storeId);
    }

    public function getPrimaryPaymentCurrency(?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getPrimaryPaymentCurrency($storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getNonPrimaryPaymentCurrencies(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->getNonPrimaryPaymentCurrencies($storeId);
    }

    public function convert(float $amount, string $currency): float
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->convert($amount, $currency);
    }

    /**
     * @deprecated 6.0.0 use convertAmount() or convert() instead.
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, bool $round = false): float
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->convertCurrency($amount, $fromCurrency, $toCurrency, $round);
    }

    public function savePaymentCurrency(PaymentCurrency $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->savePaymentCurrency($model, $runValidation);
    }

    public function deletePaymentCurrencyById(int $id): bool
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->deletePaymentCurrencyById($id);
    }

    public function convertAmount(Money $amount, Currency|string $currency, ?int $storeId = null): Money
    {
        return app(\CraftCms\Commerce\Payment\PaymentCurrencies::class)->convertAmount($amount, $currency, $storeId);
    }

    public static function registerEvents(): void
    {
        Event::listen(PaymentCurrencyRateEvent::class, static function(PaymentCurrencyRateEvent $event) {
            $legacy = Plugin::getInstance()->getPaymentCurrencies();
            if ($legacy->hasEventHandlers(self::EVENT_DEFINE_PAYMENT_CURRENCY_RATE)) {
                $legacy->trigger(self::EVENT_DEFINE_PAYMENT_CURRENCY_RATE, $event);
            }
        });
    }
}
