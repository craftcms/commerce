<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Data\Transaction;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

/**
 * @deprecated 6.0.0
 */
class ProcessPaymentEvent extends CancelableEvent
{
    /**
     * @var Order The order
     */
    public Order $order;

    /**
     * @var BasePaymentForm The payment form
     */
    public BasePaymentForm $form;

    /**
     * @var Transaction The transaction
     */
    public Transaction $transaction;

    /**
     * @var RequestResponseInterface The gateway response
     */
    public RequestResponseInterface $response;
}
