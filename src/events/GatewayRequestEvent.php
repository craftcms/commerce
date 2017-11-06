<?php

namespace craft\commerce\events;

use craft\commerce\models\Transaction;
use craft\events\CancelableEvent;

class GatewayRequestEvent extends CancelableEvent
{
    // Properties
    // =========================================================================

    /**
     * @var string Transaction type
     */
    public $type;

    /**
     * @var mixed The request
     */
    public $request;

    /**
     * @var Transaction The transaction being sent
     */
    public $transaction;
}
