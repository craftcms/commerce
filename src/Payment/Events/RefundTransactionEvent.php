<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Data\Transaction;

abstract class RefundTransactionEvent extends TransactionEvent
{
    public Transaction $refundTransaction;

    public function __construct(
        Transaction $transaction,
        public ?float $amount = null,
    ) {
        parent::__construct($transaction);
    }
}
