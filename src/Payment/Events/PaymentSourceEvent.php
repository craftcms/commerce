<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use craft\commerce\models\PaymentSource;
use CraftCms\Cms\Shared\Concerns\ValidatableEvent;

class PaymentSourceEvent
{
    use ValidatableEvent;

    public function __construct(
        public PaymentSource $paymentSource,
    ) {}
}
