<?php

namespace craft\commerce\events;

use CraftCms\Commerce\Payment\Data\Transaction;
use yii\base\Event;

/**
 * @deprecated 6.0.0
 */
class TransactionEvent extends Event
{
    /**
     * @var Transaction The transaction
     */
    public Transaction $transaction;
}
