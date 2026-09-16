<?php

namespace craft\commerce\models;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Data\PaymentCurrency} */
class_alias(\CraftCms\Commerce\Payment\Data\PaymentCurrency::class, 'craft\commerce\models\PaymentCurrency');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentCurrency extends \CraftCms\Commerce\Payment\Data\PaymentCurrency {}
}
