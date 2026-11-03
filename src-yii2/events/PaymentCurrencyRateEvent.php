<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Payment\Data\PaymentCurrency;
use CraftCms\Commerce\Payment\Data\Transaction;
use yii\base\Event;

/**
 * @deprecated 6.0.0 Use {@see \CraftCms\Commerce\Payment\Events\PaymentCurrencyRateResolving} instead.
 */
class PaymentCurrencyRateEvent extends Event
{
    /**
     * @var float The exchange rate
     */
    public float $rate;

    /**
     * @var PaymentCurrency The payment currency
     */
    public PaymentCurrency $paymentCurrency;

    /**
     * @var Transaction|null The transaction the rate is for
     */
    public ?Transaction $transaction = null;
}
