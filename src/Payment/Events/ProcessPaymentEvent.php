<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use craft\commerce\elements\Order;
use craft\commerce\models\payments\BasePaymentForm;
use CraftCms\Commerce\Payment\Models\Transaction;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;

class ProcessPaymentEvent
{
    use ValidatableEvent;

    public Transaction $transaction;
    public RequestResponseInterface $response;

    public function __construct(
        public Order $order,
        public BasePaymentForm $form,
    ) {}
}
