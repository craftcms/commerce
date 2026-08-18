<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Payment\Models\PaymentSource;

class PaymentSourceEvent
{
    use ValidatableEvent;

    public function __construct(
        public PaymentSource $paymentSource,
    ) {
    }
}
