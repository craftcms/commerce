<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Commerce\Payment\Data\Transaction;
use yii\base\Event;

class TransactionEvent extends Event
{
    public function __construct(
        public Transaction $transaction,
    ) {
    }
}
