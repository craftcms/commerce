<?php
/**
 * @link https://craftcms.com/
 * @copyright Copyright (c) Pixel & Tonic, Inc.
 * @license https://craftcms.github.io/license/
 */

namespace craft\commerce\events;

use craft\commerce\base\RequestResponseInterface;
use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use craft\events\CancelableEvent;


class ProcessPaymentEvent extends CancelableEvent
{
    /**
     * @var Order Order
     */
    public Order $order;

    /**
     * @var BasePaymentForm payment parameters
     */
    public BasePaymentForm $form;

    /**
     * @var Transaction the payment transaction
     */
    public Transaction $transaction;

    /**
     * @var RequestResponseInterface
     */
    public RequestResponseInterface $response;
}
