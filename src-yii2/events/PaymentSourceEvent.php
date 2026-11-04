<?php

namespace craft\commerce\events;

use craft\events\CancelableEvent;
use CraftCms\Commerce\Payment\Data\PaymentSource;

/**
 * @deprecated 6.0.0
 */
class PaymentSourceEvent extends CancelableEvent
{
    /**
     * @var PaymentSource The payment source
     */
    public PaymentSource $paymentSource;
}
