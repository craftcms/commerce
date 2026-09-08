<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Events;

use CraftCms\Cms\Shared\Concerns\ValidatableEvent;
use CraftCms\Commerce\Payment\Data\PaymentSource;
use yii\base\Event;

class PaymentSourceEvent extends Event
{
    use ValidatableEvent;

    public function __construct(
        public PaymentSource $paymentSource,
    ) {
    }
}
