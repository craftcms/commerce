<?php
/**
 * @link      https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license   https://craftcms.com/license
 */

namespace craft\commerce\events;

use craft\commerce\models\Transaction;
use craft\events\CancelableEvent;
use Omnipay\Common\Message\RequestInterface;

class GatewayRequestEvent extends CancelableEvent
{
    /**
     * @var string Transaction type
     */
    public $type;

    /**
     * @var RequestInterface The request
     */
    public $request;

    /**
     * @var Transaction The transaction being sent
     */
    public $transaction;
}
