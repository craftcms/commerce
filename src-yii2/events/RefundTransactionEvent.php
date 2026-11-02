<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Payment\Data\Transaction;

/**
 * @deprecated 6.0.0
 */
class RefundTransactionEvent extends TransactionEvent
{
    /**
     * @var float|null The amount to refund
     */
    public ?float $amount = null;

    /**
     * @var Transaction The refund transaction
     */
    public Transaction $refundTransaction;
}
