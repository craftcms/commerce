<?php

namespace craft\commerce\helpers;

/** @deprecated use {@see \CraftCms\Commerce\Helpers\PaymentForm} */
class_alias(\CraftCms\Commerce\Helpers\PaymentForm::class, 'craft\commerce\helpers\PaymentForm');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentForm extends \CraftCms\Commerce\Helpers\PaymentForm {}
}
