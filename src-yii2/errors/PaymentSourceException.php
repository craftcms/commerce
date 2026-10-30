<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Exceptions\PaymentSourceException} */
class_alias(\CraftCms\Commerce\Payment\Exceptions\PaymentSourceException::class, 'craft\commerce\errors\PaymentSourceException');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentSourceException extends \CraftCms\Commerce\Payment\Exceptions\PaymentSourceException {}
}
