<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Models\PaymentSource} */
class_alias(\CraftCms\Commerce\Payment\Models\PaymentSource::class, 'craft\commerce\models\PaymentSource');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentSource extends \CraftCms\Commerce\Payment\Models\PaymentSource {}
}
