<?php

namespace craft\commerce\events;

use craft\commerce\models\Transaction;
use craft\events\CancelableEvent;

/**
 * Class GatewayRequestEvent
 *
 * @author Pixel & Tonic, Inc. <support@pixelandtonic.com>
 * @since  2.0
 */
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
