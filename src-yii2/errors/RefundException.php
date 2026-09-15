<?php

namespace craft\commerce\errors;

/** @deprecated use {@see \CraftCms\Commerce\Payment\Exceptions\RefundException} */
class_alias(\CraftCms\Commerce\Payment\Exceptions\RefundException::class, 'craft\commerce\errors\RefundException');

/** @phpstan-ignore-next-line */
if (false) {
    class RefundException extends \CraftCms\Commerce\Payment\Exceptions\RefundException {}
}
