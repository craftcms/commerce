<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Forms;

use craft\commerce\models\PaymentSource;
use CraftCms\Cms\Component\Component;

abstract class BasePaymentForm extends Component
{
    public bool $savePaymentSource = false;

    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        throw new \LogicException('populateFromPaymentSource() is not supported by this gateway.');
    }
}
