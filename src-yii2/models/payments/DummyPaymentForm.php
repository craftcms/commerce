<?php

namespace craft\commerce\models\payments;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Forms\DummyPaymentForm} */
class_alias(\CraftCms\Commerce\Payment\Forms\DummyPaymentForm::class, 'craft\commerce\models\payments\DummyPaymentForm');

/** @phpstan-ignore-next-line */
if (false) {
    class DummyPaymentForm extends \CraftCms\Commerce\Payment\Forms\DummyPaymentForm {}
}
