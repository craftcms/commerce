<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use craft\commerce\models\Transaction;

class TransactionEvent
{
    public Transaction $transaction;
}
