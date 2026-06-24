<?php

namespace craft\commerce\services;

use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use CraftCms\Commerce\Services\PaymentCurrencies as NewPaymentCurrencies;
use Illuminate\Support\Collection;
use Money\Currency;
use Money\Money;
use yii\base\Component;

/**
 * @deprecated 6.0.0 use `app(\CraftCms\Commerce\Services\PaymentCurrencies::class)` instead.
 */
class PaymentCurrencies extends Component
{
    private function impl(): NewPaymentCurrencies
    {
        return app(NewPaymentCurrencies::class);
    }

    public function getPaymentCurrencyById(int $id, ?int $storeId = null): ?PaymentCurrency
    {
        return $this->impl()->getPaymentCurrencyById($id, $storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getAllPaymentCurrencies(?int $storeId = null): Collection
    {
        return $this->impl()->getAllPaymentCurrencies($storeId);
    }

    public function getPaymentCurrencyByIso(string $iso, ?int $storeId = null): ?PaymentCurrency
    {
        return $this->impl()->getPaymentCurrencyByIso($iso, $storeId);
    }

    public function getPrimaryPaymentCurrencyIso(?int $storeId = null): string
    {
        return $this->impl()->getPrimaryPaymentCurrencyIso($storeId);
    }

    public function getPrimaryPaymentCurrency(?int $storeId = null): ?PaymentCurrency
    {
        return $this->impl()->getPrimaryPaymentCurrency($storeId);
    }

    /**
     * @return Collection<int, PaymentCurrency>
     */
    public function getNonPrimaryPaymentCurrencies(?int $storeId = null): Collection
    {
        return $this->impl()->getNonPrimaryPaymentCurrencies($storeId);
    }

    public function convert(float $amount, string $currency): float
    {
        return $this->impl()->convert($amount, $currency);
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
        $from = $this->impl()->getPaymentCurrencyByIso($fromCurrency);
        $to = $this->impl()->getPaymentCurrencyByIso($toCurrency);

        if (!$from || !$to) {
            throw new \RuntimeException('Currency not found: ' . ($from ? $toCurrency : $fromCurrency));
        }

        $primary = $this->impl()->getPrimaryPaymentCurrency();
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
        return $this->impl()->savePaymentCurrency($model, $runValidation);
    }

    public function deletePaymentCurrencyById(int $id): bool
    {
        return $this->impl()->deletePaymentCurrencyById($id);
    }

    public function convertAmount(Money $amount, Currency|string $currency, ?int $storeId = null): Money
    {
        return $this->impl()->convertAmount($amount, $currency, $storeId);
    }
}
