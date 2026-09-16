<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException} */
class_alias(\CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException::class, 'craft\commerce\errors\PaymentSourceCreatedLaterException');

/** @phpstan-ignore-next-line */
if (false) {
    class PaymentSourceCreatedLaterException extends \CraftCms\Commerce\Payment\Exceptions\PaymentSourceCreatedLaterException {}
}
