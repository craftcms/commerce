<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use craft\commerce\models\Transaction;
use CraftCms\Commerce\Payment\Models\PaymentCurrency;

class PaymentCurrencyRateEvent
{
    /**
     * The rate that will be used. Set this to override the rate.
     */
    public float $rate;

    /**
     * The payment currency the rate is being resolved for.
     */
    public PaymentCurrency $paymentCurrency;

    /**
     * The transaction the rate is being resolved for, if any.
     */
    public ?Transaction $transaction = null;
}
