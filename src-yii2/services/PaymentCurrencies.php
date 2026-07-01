<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use Illuminate\Support\Collection;
use Money\Currency;
use Money\Money;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\PaymentCurrencies::class)` instead.
 */
class PaymentCurrencies extends Component
{
    public function getPaymentCurrencyById(int $id, ?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getPaymentCurrencyById($id, $storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getAllPaymentCurrencies(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getAllPaymentCurrencies($storeId);
    }

    public function getPaymentCurrencyByIso(string $iso, ?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getPaymentCurrencyByIso($iso, $storeId);
    }

    public function getPrimaryPaymentCurrencyIso(?int $storeId = null): string
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getPrimaryPaymentCurrencyIso($storeId);
    }

    public function getPrimaryPaymentCurrency(?int $storeId = null): ?PaymentCurrency
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getPrimaryPaymentCurrency($storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getNonPrimaryPaymentCurrencies(?int $storeId = null): Collection
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->getNonPrimaryPaymentCurrencies($storeId);
    }

    public function convert(float $amount, string $currency): float
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->convert($amount, $currency);
    }

    /**
     * Legacy convertCurrency for src-yii2 callers (Order element, OrdersController).
     * The new service drops this; these callers will be updated when their
     * classes migrate to src/. Logic ported verbatim from Commerce 5.x.
     *
     * @deprecated 6.0.0 use convertAmount() or convert() instead.
     */
    public function convertCurrency(float $amount, string $fromCurrency, string $toCurrency, bool $round = false): float
    {
        $svc = app(\CraftCms\Commerce\Services\PaymentCurrencies::class);
        $from = $svc->getPaymentCurrencyByIso($fromCurrency);
        $to = $svc->getPaymentCurrencyByIso($toCurrency);

        if (!$from || !$to) {
            throw new \RuntimeException('Currency not found: ' . ($from ? $toCurrency : $fromCurrency));
        }

        $primary = $svc->getPrimaryPaymentCurrency();
        if ($primary && $primary->iso !== $fromCurrency) {
            // amount is not in primary currency; normalize back to primary first
            $amount /= $from->rate;
        }

        $result = $amount * $to->rate;

        if ($round) {
            return \craft\commerce\helpers\Currency::round($result, $to);
        }

        return $result;
    }

    public function savePaymentCurrency(PaymentCurrency $model, bool $runValidation = true): bool
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->savePaymentCurrency($model, $runValidation);
    }

    public function deletePaymentCurrencyById(int $id): bool
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->deletePaymentCurrencyById($id);
    }

    public function convertAmount(Money $amount, Currency|string $currency, ?int $storeId = null): Money
    {
        return app(\CraftCms\Commerce\Services\PaymentCurrencies::class)->convertAmount($amount, $currency, $storeId);
    }
}
