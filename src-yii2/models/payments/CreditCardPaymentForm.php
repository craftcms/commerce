<?php

namespace craft\commerce\models\payments;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm} */
class_alias(\CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm::class, 'craft\commerce\models\payments\CreditCardPaymentForm');

/** @phpstan-ignore-next-line */
if (false) {
    class CreditCardPaymentForm extends \CraftCms\Commerce\Payment\Forms\CreditCardPaymentForm {}
}
