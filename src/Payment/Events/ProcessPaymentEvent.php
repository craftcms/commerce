<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use craft\commerce\models\Transaction;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

class ProcessPaymentEvent
{
    use ValidatableEvent;

    public Order $order;
    public BasePaymentForm $form;
    public Transaction $transaction;
    public RequestResponseInterface $response;
}
