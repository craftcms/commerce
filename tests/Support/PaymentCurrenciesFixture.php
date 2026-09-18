<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Tests\Support;

use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\PaymentCurrencies;
use CraftCms\Commerce\Store\Stores;
use RuntimeException;

/**
 * Builds two non-primary payment currencies (EUR at 0.5, AUD at 1.3) on top of the primary
 * store's default USD currency, for `PaymentCurrencies` service tests.
 */
class PaymentCurrenciesFixture
{
    public PaymentCurrency $eur;

    public PaymentCurrency $aud;

    public int $storeId;

    public static function seed(): self
    {
        $fixture = new self();
        $fixture->build();

        return $fixture;
    }

    private function build(): void
    {
        $this->storeId = app(Stores::class)->getPrimaryStore()->id;

        $this->eur = $this->createCurrency('EUR', 0.5);
        $this->aud = $this->createCurrency('AUD', 1.3);
    }

    private function createCurrency(string $iso, float $rate): PaymentCurrency
    {
        $currency = new PaymentCurrency();
        $currency->iso = $iso;
        $currency->rate = $rate;
        $currency->storeId = $this->storeId;

        if (!app(PaymentCurrencies::class)->savePaymentCurrency($currency)) {
            throw new RuntimeException('Could not save payment currency: ' . $iso);
        }

        return $currency;
    }
}
