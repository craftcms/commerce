<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Exceptions\PaymentException} */
class_alias(\CraftCms\Commerce\Payment\Exceptions\PaymentException::class, 'craft\commerce\errors\PaymentException');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentException extends \CraftCms\Commerce\Payment\Exceptions\PaymentException {}
}
