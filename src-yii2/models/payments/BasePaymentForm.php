<?php

namespace craft\commerce\models\payments;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Forms\BasePaymentForm} */
class_alias(\CraftCms\Commerce\Payment\Forms\BasePaymentForm::class, 'craft\commerce\models\payments\BasePaymentForm');

/** @phpstan-ignore-next-line */
if (false) {
    class BasePaymentForm extends \CraftCms\Commerce\Payment\Forms\BasePaymentForm {}
}
