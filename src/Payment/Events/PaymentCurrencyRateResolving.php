<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\Data\Transaction;

/**
 * Dispatched when resolving the exchange rate for a payment currency. Listeners may change `$rate`.
 */
class PaymentCurrencyRateResolving
{
    public function __construct(
        public float $rate,
        public PaymentCurrency $paymentCurrency,
        public ?Transaction $transaction = null,
    ) {
    }
}
