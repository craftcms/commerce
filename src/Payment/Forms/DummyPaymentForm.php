<?php

declare(strict_types=1);

namespace CraftCms\Commerce\Payment\Forms;

use CraftCms\Commerce\Payment\Models\PaymentSource;

class DummyPaymentForm extends CreditCardPaymentForm
{
    #[\Override]
    public function populateFromPaymentSource(PaymentSource $paymentSource): void
    {
        $this->token = (string)$paymentSource->id;
    }

    #[\Override]
    public function getRules(): array
    {
        if ($this->token) {
            return [];
        }

        return parent::getRules();
    }
}
