<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Models\PaymentCurrency;
use CraftCms\Commerce\Payment\Models\Transaction;

class PaymentCurrencyRateEvent
{
    public function __construct(
        public float $rate,
        public PaymentCurrency $paymentCurrency,
        public ?Transaction $transaction = null,
    ) {
    }
}
