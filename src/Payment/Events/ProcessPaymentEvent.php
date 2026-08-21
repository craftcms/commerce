<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Order\Elements\Order;
use CraftCms\Commerce\Payment\Forms\BasePaymentForm;
use CraftCms\Commerce\Payment\Gateway\Contracts\RequestResponseInterface;
use CraftCms\Commerce\Payment\Models\Transaction;

class ProcessPaymentEvent
{
    use ValidatableEvent;

    public Transaction $transaction;
    public RequestResponseInterface $response;

    public function __construct(
        public Order $order,
        public BasePaymentForm $form,
    ) {
    }
}
