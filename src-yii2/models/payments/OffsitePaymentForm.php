<?php

namespace craft\commerce\models\payments;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Forms\OffsitePaymentForm} */
class_alias(\CraftCms\Commerce\Payment\Forms\OffsitePaymentForm::class, 'craft\commerce\models\payments\OffsitePaymentForm');

/** @phpstan-ignore-next-line */
if (false) {
    class OffsitePaymentForm extends \CraftCms\Commerce\Payment\Forms\OffsitePaymentForm {}
}
