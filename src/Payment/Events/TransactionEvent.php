<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Data\Transaction;

class TransactionEvent
{
    public function __construct(
        public Transaction $transaction,
    ) {
    }
}
