<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Data\PaymentSource} */
class_alias(\CraftCms\Commerce\Payment\Data\PaymentSource::class, 'craft\commerce\models\PaymentSource');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentSource extends \CraftCms\Commerce\Payment\Data\PaymentSource {}
}
